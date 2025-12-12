<?php

declare(strict_types=1);

namespace WPClaw\Tools;

use WPClaw\Agent\Context;

/**
 * Built in tool for creating draft posts as current user.
 */
final class CreateDraftPostTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $insertPost;

    /**
     * @var callable
     */
    private $currentUserId;

    /**
     * @var callable
     */
    private $getEditLink;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $insertPost = null,
        ?callable $currentUserId = null,
        ?callable $getEditLink = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);

        $this->insertPost = $insertPost ?? static fn (array $post): mixed => wp_insert_post($post, true);
        $this->currentUserId = $currentUserId ?? static fn (): int => (int) get_current_user_id();
        $this->getEditLink = $getEditLink ?? static fn (int $id): string => (string) get_edit_post_link($id, '');
    }

    public function get_name(): string
    {
        return 'create_draft_post';
    }

    public function get_description(): string
    {
        return 'Creates a new draft post owned by current user.';
    }

    public function get_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => 200,
                ],
                'content' => [
                    'type' => 'string',
                    'maxLength' => 20000,
                ],
                'excerpt' => [
                    'type' => 'string',
                    'maxLength' => 5000,
                ],
            ],
            'required' => ['title'],
            'additionalProperties' => false,
        ];
    }

    public function get_required_capability(): string
    {
        return 'edit_posts';
    }

    public function execute(array $args, Context $context): ExecutionResult
    {
        if (! $this->user_can($this->get_required_capability())) {
            return ExecutionResult::denied('You do not have permission to create draft posts.');
        }

        $validationError = $this->validate_arguments($args);
        if ($validationError !== null) {
            return $validationError;
        }

        $postData = [
            'post_title' => (string) $args['title'],
            'post_content' => (string) ($args['content'] ?? ''),
            'post_excerpt' => (string) ($args['excerpt'] ?? ''),
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => (int) call_user_func($this->currentUserId),
        ];

        $result = call_user_func($this->insertPost, $postData);

        if (is_object($result) && function_exists('is_wp_error') && is_wp_error($result)) {
            return ExecutionResult::error((string) $result->get_error_message(), 'post_insert_failed');
        }

        if (! is_int($result) || $result < 1) {
            return ExecutionResult::error('Draft post was not created.', 'post_insert_failed');
        }

        return ExecutionResult::success([
            'id' => $result,
            'status' => 'draft',
            'author_id' => $postData['post_author'],
            'edit_link' => (string) call_user_func($this->getEditLink, $result),
        ]);
    }
}
