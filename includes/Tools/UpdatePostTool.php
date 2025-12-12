<?php

declare(strict_types=1);

namespace WPClaw\Tools;

use WPClaw\Agent\Context;

/**
 * Built in tool for updating an existing post by id.
 */
final class UpdatePostTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $getPost;

    /**
     * @var callable
     */
    private $canEditPost;

    /**
     * @var callable
     */
    private $updatePost;

    /**
     * @var callable
     */
    private $getEditLink;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $getPost = null,
        ?callable $canEditPost = null,
        ?callable $updatePost = null,
        ?callable $getEditLink = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);

        $this->getPost = $getPost ?? static fn (int $id): mixed => get_post($id);
        $this->canEditPost = $canEditPost ?? static fn (int $id): bool => current_user_can('edit_post', $id);
        $this->updatePost = $updatePost ?? static fn (array $post): mixed => wp_update_post($post, true);
        $this->getEditLink = $getEditLink ?? static fn (int $id): string => (string) get_edit_post_link($id, '');
    }

    public function get_name(): string
    {
        return 'update_post';
    }

    public function get_description(): string
    {
        return 'Updates an existing post by id. Supports title, content, excerpt and status.';
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
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'publish', 'pending', 'private'],
                ],
            ],
            'required' => ['id'],
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
            return ExecutionResult::denied('You do not have permission to edit posts.');
        }

        $validationError = $this->validate_arguments($args);
        if ($validationError !== null) {
            return $validationError;
        }

        $postId = (int) $args['id'];
        $post = call_user_func($this->getPost, $postId);
        if (! is_object($post)) {
            return ExecutionResult::error('Post not found.', 'post_not_found');
        }

        if (! (bool) call_user_func($this->canEditPost, $postId)) {
            return ExecutionResult::denied('You do not have permission to edit this post.');
        }

        $updateData = ['ID' => $postId];
        $updatedFields = [];

        if (array_key_exists('title', $args)) {
            $updateData['post_title'] = (string) $args['title'];
            $updatedFields[] = 'title';
        }
        if (array_key_exists('content', $args)) {
            $updateData['post_content'] = (string) $args['content'];
            $updatedFields[] = 'content';
        }
        if (array_key_exists('excerpt', $args)) {
            $updateData['post_excerpt'] = (string) $args['excerpt'];
            $updatedFields[] = 'excerpt';
        }
        if (array_key_exists('status', $args)) {
            $updateData['post_status'] = (string) $args['status'];
            $updatedFields[] = 'status';
        }

        if ($updatedFields === []) {
            return ExecutionResult::error(
                'At least one editable field is required: title, content, excerpt or status.',
                'no_changes_requested'
            );
        }

        $result = call_user_func($this->updatePost, $updateData);

        if (is_object($result) && function_exists('is_wp_error') && is_wp_error($result)) {
            return ExecutionResult::error((string) $result->get_error_message(), 'post_update_failed');
        }

        if (! is_int($result) || $result < 1) {
            return ExecutionResult::error('Post was not updated.', 'post_update_failed');
        }

        $updatedPost = call_user_func($this->getPost, $postId);
        $status = is_object($updatedPost) && isset($updatedPost->post_status)
            ? (string) $updatedPost->post_status
            : (string) ($args['status'] ?? '');

        return ExecutionResult::success([
            'id' => $postId,
            'status' => $status,
            'updated_fields' => $updatedFields,
            'edit_link' => (string) call_user_func($this->getEditLink, $postId),
        ]);
    }
}
