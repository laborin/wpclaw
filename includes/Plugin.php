<?php

declare(strict_types=1);

namespace WPClaw;

use WPClaw\Block\ReactRenderer;
use WPClaw\Block\Registration as BlockRegistration;
use WPClaw\Rest\Controller;
use WPClaw\Security\RoleGate;
use WPClaw\Settings\Fields;
use WPClaw\Settings\Options;
use WPClaw\Settings\Page;
use WPClaw\Tools\CreateDraftPostTool;
use WPClaw\Tools\DeletePostTool;
use WPClaw\Tools\GetCurrentUserTool;
use WPClaw\Tools\GetPostTool;
use WPClaw\Tools\GetSiteStatsTool;
use WPClaw\Tools\ListRecentCommentsTool;
use WPClaw\Tools\Registry;
use WPClaw\Tools\SearchMediaTool;
use WPClaw\Tools\SearchPostsTool;
use WPClaw\Tools\UpdatePostTool;

/**
 * Main plugin composition root that wires services and register hooks.
 */
final class Plugin
{
    private Controller $restController;

    private Page $settingsPage;

    private BlockRegistration $blockRegistration;

    public function __construct(
        ?Controller $restController = null,
        ?Page $settingsPage = null,
        ?BlockRegistration $blockRegistration = null
    ) {
        $this->restController = $restController ?? new Controller();

        $roleGate = new RoleGate();

        if ($settingsPage !== null) {
            $this->settingsPage = $settingsPage;
        } else {
            $toolRegistry = new Registry([
                new SearchPostsTool(),
                new GetPostTool(),
                new ListRecentCommentsTool(),
                new GetSiteStatsTool(),
                new SearchMediaTool(),
                new GetCurrentUserTool(),
                new CreateDraftPostTool(),
                new UpdatePostTool(),
                new DeletePostTool(),
            ]);

            $options = new Options();
            $fields = new Fields($options, $toolRegistry);

            $this->settingsPage = new Page($fields);
        }

        $this->blockRegistration = $blockRegistration ?? new BlockRegistration(
            new ReactRenderer($roleGate)
        );
    }

    public function register_hooks(): void
    {
        add_action('init', [$this->blockRegistration, 'register']);
        add_action('rest_api_init', [$this->restController, 'register_routes']);
        $this->settingsPage->register_hooks();
    }
}
