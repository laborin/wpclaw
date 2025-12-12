<?php

declare(strict_types=1);

namespace WPClaw\Tools;

use WPClaw\Agent\Context;

/**
 * Built in tool that searches published posts by text and filters.
 */
final class SearchPostsTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $getPosts;

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
    private $getPostType;

    /**
     * @var callable
     */
    private $getPostDate;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $getPosts = null,
        ?callable $getPermalink = null,
        ?callable $getTitle = null,
        ?callable $getExcerpt = null,
        ?callable $getPostType = null,
        ?callable $getPostDate = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);

        $this->getPosts = $getPosts ?? static fn (array $query): array => get_posts($query);
        $this->getPermalink = $getPermalink ?? static fn (int $postId): string => (string) get_permalink($postId);
        $this->getTitle = $getTitle ?? static fn (object $post): string => (string) get_the_title($post);
        $this->getExcerpt = $getExcerpt ?? static fn (object $post): string => (string) get_the_excerpt($post);
        $this->getPostType = $getPostType ?? static fn (object $post): string => (string) get_post_type($post);
        $this->getPostDate = $getPostDate ?? static fn (object $post): string => (string) ($post->post_date_gmt ?? $post->post_date ?? '');
    }

    public function get_name(): string
    {
        return 'search_posts';
    }

    public function get_description(): string
    {
        return 'Searches published site posts using keyword and optional filters.';
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
                'post_type' => [
                    'type' => 'string',
                    'enum' => ['post', 'page'],
                ],
                'date_range' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'from' => [
                            'type' => 'string',
                        ],
                        'to' => [
                            'type' => 'string',
                        ],
                    ],
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
        return 'read';
    }

    public function execute(array $args, Context $context): ExecutionResult
    {
        if (! $this->user_can($this->get_required_capability())) {
            return ExecutionResult::denied('You do not have permission to search posts.');
        }

        $validationError = $this->validate_arguments($args);
        if ($validationError !== null) {
            return $validationError;
        }

        $query = [
            'post_status' => 'publish',
            'post_type' => (string) ($args['post_type'] ?? 'post'),
            'posts_per_page' => (int) ($args['limit'] ?? 10),
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ];

        $keyword = isset($args['keyword']) ? trim((string) $args['keyword']) : '';
        if ($keyword !== '') {
            $query['s'] = $keyword;
        }

        $dateRange = $args['date_range'] ?? null;
        if (is_array($dateRange)) {
            $dateQuery = [];
            if (isset($dateRange['from']) && is_string($dateRange['from']) && $dateRange['from'] !== '') {
                $dateQuery['after'] = $dateRange['from'];
            }
            if (isset($dateRange['to']) && is_string($dateRange['to']) && $dateRange['to'] !== '') {
                $dateQuery['before'] = $dateRange['to'];
            }
            if ($dateQuery !== []) {
                $dateQuery['inclusive'] = true;
                $query['date_query'] = [$dateQuery];
            }
        }

        try {
            $posts = call_user_func($this->getPosts, $query);
        } catch (\Throwable $exception) {
            return ExecutionResult::error('Post search failed: ' . $exception->getMessage(), 'tool_runtime_error');
        }

        $items = [];
        foreach ($posts as $post) {
            if (! is_object($post)) {
                continue;
            }

            $postId = (int) ($post->ID ?? 0);
            if ($postId < 1) {
                continue;
            }

            $items[] = [
                'id' => $postId,
                'title' => (string) call_user_func($this->getTitle, $post),
                'url' => (string) call_user_func($this->getPermalink, $postId),
                'excerpt' => (string) call_user_func($this->getExcerpt, $post),
                'post_type' => (string) call_user_func($this->getPostType, $post),
                'date' => (string) call_user_func($this->getPostDate, $post),
            ];
        }

        return ExecutionResult::success([
            'items' => $items,
            'count' => count($items),
            'applied_filters' => [
                'keyword' => $keyword,
                'post_type' => $query['post_type'],
                'limit' => $query['posts_per_page'],
            ],
        ]);
    }
}
