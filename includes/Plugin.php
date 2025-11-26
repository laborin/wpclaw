<?php

declare(strict_types=1);

namespace WPNativeAgent;

use WPNativeAgent\Block\InteractivityRenderer;
use WPNativeAgent\Block\ReactRenderer;
use WPNativeAgent\Block\Registration as BlockRegistration;
use WPNativeAgent\Rest\Controller;
use WPNativeAgent\Security\RoleGate;
use WPNativeAgent\Settings\Fields;
use WPNativeAgent\Settings\Options;
use WPNativeAgent\Settings\Page;
use WPNativeAgent\Tools\CreateDraftPostTool;
use WPNativeAgent\Tools\DeletePostTool;
use WPNativeAgent\Tools\GetCurrentUserTool;
use WPNativeAgent\Tools\GetPostTool;
use WPNativeAgent\Tools\GetSiteStatsTool;
use WPNativeAgent\Tools\ListRecentCommentsTool;
use WPNativeAgent\Tools\Registry;
use WPNativeAgent\Tools\SearchMediaTool;
use WPNativeAgent\Tools\SearchPostsTool;
use WPNativeAgent\Tools\UpdatePostTool;

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
            new ReactRenderer($roleGate),
            new InteractivityRenderer($roleGate)
        );
    }

    public function register_hooks(): void
    {
        add_action('init', [$this->blockRegistration, 'register']);
        add_action('rest_api_init', [$this->restController, 'register_routes']);
        $this->settingsPage->register_hooks();
    }
}
