<?php

declare(strict_types=1);

namespace WPClaw\Tools;

use WPClaw\Agent\Context;

/**
 * Built in tool for searching media library assets.
 */
final class SearchMediaTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $getAttachments;

    /**
     * @var callable
     */
    private $getAttachmentUrl;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $getAttachments = null,
        ?callable $getAttachmentUrl = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);

        $this->getAttachments = $getAttachments ?? static fn (array $query): array => get_posts($query);
        $this->getAttachmentUrl = $getAttachmentUrl ?? static fn (int $id): string => (string) wp_get_attachment_url($id);
    }

    public function get_name(): string
    {
        return 'search_media';
    }

    public function get_description(): string
    {
        return 'Searches media library and returns metadata and urls.';
    }

    public function get_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 200,
                ],
                'mime_type' => [
                    'type' => 'string',
                    'maxLength' => 120,
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 20,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function get_required_capability(): string
    {
        return 'upload_files';
    }

    public function execute(array $args, Context $context): ExecutionResult
    {
        if (! $this->user_can($this->get_required_capability())) {
            return ExecutionResult::denied('You do not have permission to search media library.');
        }

        $validationError = $this->validate_arguments($args);
        if ($validationError !== null) {
            return $validationError;
        }

        $limit = (int) ($args['limit'] ?? 10);

        $query = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ];

        if (isset($args['keyword']) && is_string($args['keyword']) && trim($args['keyword']) !== '') {
            $query['s'] = trim($args['keyword']);
        }

        if (isset($args['mime_type']) && is_string($args['mime_type']) && trim($args['mime_type']) !== '') {
            $query['post_mime_type'] = trim($args['mime_type']);
        }

        $attachments = call_user_func($this->getAttachments, $query);

        $items = [];
        foreach ($attachments as $attachment) {
            if (! is_object($attachment)) {
                continue;
            }

            $id = (int) ($attachment->ID ?? 0);
            if ($id < 1) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'title' => (string) ($attachment->post_title ?? ''),
                'url' => (string) call_user_func($this->getAttachmentUrl, $id),
                'mime_type' => (string) ($attachment->post_mime_type ?? ''),
                'date' => (string) ($attachment->post_date_gmt ?? $attachment->post_date ?? ''),
            ];
        }

        return ExecutionResult::success([
            'items' => $items,
            'count' => count($items),
        ]);
    }
}
