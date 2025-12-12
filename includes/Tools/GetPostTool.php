<?php

declare(strict_types=1);

namespace WPClaw\Tools;

use WPClaw\Agent\Context;

/**
 * Built in tool for loading one post details by id.
 */
final class GetPostTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $getPost;

    /**
     * @var callable
     */
    private $getPermalink;

    /**
     * @var callable
     */
    private $getTitle;

    /**
     * @var callable
     */
    private $getExcerpt;

    /**
     * @var callable
     */
    private $getContent;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $getPost = null,
        ?callable $getPermalink = null,
        ?callable $getTitle = null,
        ?callable $getExcerpt = null,
        ?callable $getContent = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);

        $this->getPost = $getPost ?? static fn (int $id): mixed => get_post($id);
        $this->getPermalink = $getPermalink ?? static fn (int $id): string => (string) get_permalink($id);
        $this->getTitle = $getTitle ?? static fn (object $post): string => (string) get_the_title($post);
        $this->getExcerpt = $getExcerpt ?? static fn (object $post): string => (string) get_the_excerpt($post);
        $this->getContent = $getContent ?? static fn (object $post): string => (string) ($post->post_content ?? '');
    }

    public function get_name(): string
    {
        return 'get_post';
    }

    public function get_description(): string
    {
        return 'Gets one post by id and returns public fields.';
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
            ],
            'required' => ['id'],
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
            return ExecutionResult::denied('You do not have permission to read posts.');
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

        $status = (string) ($post->post_status ?? '');
        $isPublic = $status === 'publish';

        if (! $isPublic && ! $this->user_can('edit_posts')) {
            return ExecutionResult::denied('You do not have permission to view this post status.');
        }

        return ExecutionResult::success([
            'id' => (int) ($post->ID ?? $postId),
            'title' => (string) call_user_func($this->getTitle, $post),
            'excerpt' => (string) call_user_func($this->getExcerpt, $post),
            'content' => (string) call_user_func($this->getContent, $post),
            'url' => (string) call_user_func($this->getPermalink, (int) ($post->ID ?? $postId)),
            'post_type' => (string) ($post->post_type ?? 'post'),
            'status' => $status,
            'date' => (string) ($post->post_date_gmt ?? $post->post_date ?? ''),
        ]);
    }
}
