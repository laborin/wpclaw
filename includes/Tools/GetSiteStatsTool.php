<?php

declare(strict_types=1);

namespace WPNativeAgent\Tools;

use WPNativeAgent\Agent\Context;

/**
 * Built in tool for aggregate site statistics.
 */
final class GetSiteStatsTool extends AbstractTool
{
    /**
     * @var callable
     */
    private $countPosts;

    /**
     * @var callable
     */
    private $countUsers;

    /**
     * @var callable
     */
    private $countComments;

    public function __construct(
        ?SchemaValidator $schemaValidator = null,
        ?callable $capabilityChecker = null,
        ?callable $countPosts = null,
        ?callable $countUsers = null,
        ?callable $countComments = null
    ) {
        parent::__construct($schemaValidator, $capabilityChecker);
        $this->countPosts = $countPosts ?? static fn (): object => wp_count_posts('post');
        $this->countUsers = $countUsers ?? static fn (): array => count_users();
        $this->countComments = $countComments ?? static fn (): object => wp_count_comments();
    }

    public function get_name(): string
    {
        return 'get_site_stats';
    }

    public function get_description(): string
    {
        return 'Returns aggregate counts for posts, users and comments.';
    }

    public function get_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
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
            return ExecutionResult::denied('You do not have permission to read site stats.');
        }

        $validationError = $this->validate_arguments($args);
        if ($validationError !== null) {
            return $validationError;
        }

        $postCounts = call_user_func($this->countPosts);
        $userCounts = call_user_func($this->countUsers);
        $commentCounts = call_user_func($this->countComments);

        return ExecutionResult::success([
            'posts' => is_object($postCounts) ? get_object_vars($postCounts) : [],
            'users' => [
                'total' => (int) ($userCounts['total_users'] ?? 0),
                'by_role' => is_array($userCounts['avail_roles'] ?? null) ? $userCounts['avail_roles'] : [],
            ],
            'comments' => is_object($commentCounts) ? get_object_vars($commentCounts) : [],
        ]);
    }
}
