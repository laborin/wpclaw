<?php

declare(strict_types=1);

namespace WPClaw\Tools;

use WPClaw\Agent\Context;

/**
 * Built in tool for deleting an existing post by id.
 */
final class DeletePostTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $getPost;

    /**
     * @var callable
     */
    private $canDeletePost;

    /**
     * @var callable
     */
    private $trashPost;

    /**
     * @var callable
     */
    private $deletePost;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $getPost = null,
        ?callable $canDeletePost = null,
        ?callable $trashPost = null,
        ?callable $deletePost = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);

        $this->getPost = $getPost ?? static fn (int $id): mixed => get_post($id);
        $this->canDeletePost = $canDeletePost ?? static fn (int $id): bool => current_user_can('delete_post', $id);
        $this->trashPost = $trashPost ?? static fn (int $id): mixed => wp_trash_post($id);
        $this->deletePost = $deletePost ?? static fn (int $id): mixed => wp_delete_post($id, true);
    }

    public function get_name(): string
    {
        return 'delete_post';
    }

    public function get_description(): string
    {
        return 'Deletes an existing post, page, or custom post type item by id. Uses trash by default.';
    }

    public function get_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'force' => [
                    'type' => 'boolean',
                ],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }

    public function get_required_capability(): string
    {
        return 'delete_posts';
    }

    public function execute(array $args, Context $context): ExecutionResult
    {
        if (! $this->user_can($this->get_required_capability())) {
            return ExecutionResult::denied('You do not have permission to delete posts.');
        }

        $validationError = $this->validate_arguments($args);
        if ($validationError !== null) {
            return $validationError;
        }

        $postId = (int) $args['id'];
        $forceDelete = (bool) ($args['force'] ?? false);

        $post = call_user_func($this->getPost, $postId);
        if (! is_object($post)) {
            return ExecutionResult::error('Post not found.', 'post_not_found');
        }

        if (! (bool) call_user_func($this->canDeletePost, $postId)) {
            return ExecutionResult::denied('You do not have permission to delete this post.');
        }

        $previousStatus = (string) ($post->post_status ?? '');
        $postType = (string) ($post->post_type ?? 'post');

        if ($forceDelete) {
            $deleted = call_user_func($this->deletePost, $postId);

            if (is_object($deleted) && function_exists('is_wp_error') && is_wp_error($deleted)) {
                return ExecutionResult::error((string) $deleted->get_error_message(), 'post_delete_failed');
            }

            if ($deleted === false || $deleted === null) {
                return ExecutionResult::error('Post was not deleted.', 'post_delete_failed');
            }

            return ExecutionResult::success([
                'id' => $postId,
                'post_type' => $postType,
                'previous_status' => $previousStatus,
                'status' => 'deleted',
                'force' => true,
            ]);
        }

        if ($previousStatus === 'trash') {
            return ExecutionResult::success([
                'id' => $postId,
                'post_type' => $postType,
                'previous_status' => $previousStatus,
                'status' => 'trash',
                'force' => false,
            ]);
        }

        $trashed = call_user_func($this->trashPost, $postId);

        if (is_object($trashed) && function_exists('is_wp_error') && is_wp_error($trashed)) {
            return ExecutionResult::error((string) $trashed->get_error_message(), 'post_delete_failed');
        }

        if ($trashed === false || $trashed === null) {
            return ExecutionResult::error('Post was not moved to trash.', 'post_delete_failed');
        }

        return ExecutionResult::success([
            'id' => $postId,
            'post_type' => $postType,
            'previous_status' => $previousStatus,
            'status' => 'trash',
            'force' => false,
        ]);
    }
}
