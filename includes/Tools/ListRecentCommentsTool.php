<?php

declare(strict_types=1);

namespace WPClaw\Tools;

use WPClaw\Agent\Context;

/**
 * Built in tool for listing recent comments with role-based visibility.
 */
final class ListRecentCommentsTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $getComments;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $getComments = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);
        $this->getComments = $getComments ?? static fn (array $query): array => get_comments($query);
    }

    public function get_name(): string
    {
        return 'list_recent_comments';
    }

    public function get_description(): string
    {
        return 'Returns recent comments visible for current user permissions.';
    }

    public function get_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function get_required_capability(): string
    {
        return 'read';
    }

    public function execute(array $args, Context $context): ExecutionResult
    {
        if (! $this->user_can($this->get_required_capability())) {
            return ExecutionResult::denied('You do not have permission to read comments.');
        }

        $validationError = $this->validate_arguments($args);
        if ($validationError !== null) {
            return $validationError;
        }

        $limit = (int) ($args['limit'] ?? 10);
        $status = $this->user_can('moderate_comments') ? 'all' : 'approve';

        $comments = call_user_func($this->getComments, [
            'number' => $limit,
            'status' => $status,
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC',
        ]);

        $items = [];
        foreach ($comments as $comment) {
            if (! is_object($comment)) {
                continue;
            }

            $items[] = [
                'id' => (int) ($comment->comment_ID ?? 0),
                'post_id' => (int) ($comment->comment_post_ID ?? 0),
                'author' => (string) ($comment->comment_author ?? ''),
                'content' => (string) ($comment->comment_content ?? ''),
                'status' => (string) ($comment->comment_approved ?? ''),
                'date' => (string) ($comment->comment_date_gmt ?? $comment->comment_date ?? ''),
            ];
        }

        return ExecutionResult::success([
            'items' => $items,
            'count' => count($items),
            'status_scope' => $status,
        ]);
    }
}
