<?php

declare(strict_types=1);

namespace WPClaw\Rest;

use WPClaw\Agent\Context;
use WPClaw\Agent\Loop;
use WPClaw\Provider\ProviderFactory;
use WPClaw\Security\CancelSignal;
use WPClaw\Security\InputSanitizer;
use WPClaw\Security\RateLimiter;
use WPClaw\Security\RoleGate;
use WPClaw\Settings\Options;
use WPClaw\Session\MessageRepository;
use WPClaw\Session\SessionRepository;
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
 * REST controller that wires all plugin routes.
 */
final class Controller
{
    private ChatEndpoint $chatEndpoint;

    private HistoryEndpoint $historyEndpoint;

    private CancelEndpoint $cancelEndpoint;

    public function __construct(
        ?ChatEndpoint $chatEndpoint = null,
        ?HistoryEndpoint $historyEndpoint = null,
        ?CancelEndpoint $cancelEndpoint = null
    ) {
        if ($chatEndpoint !== null && $historyEndpoint !== null && $cancelEndpoint !== null) {
            $this->chatEndpoint = $chatEndpoint;
            $this->historyEndpoint = $historyEndpoint;
            $this->cancelEndpoint = $cancelEndpoint;
            return;
        }

        global $wpdb;

        $sessionRepository = new SessionRepository($wpdb);
        $messageRepository = new MessageRepository($wpdb);
        $roleGate = new RoleGate();
        $guard = new Guard($roleGate);
        $providerFactory = new ProviderFactory();
        $options = new Options();

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

        $runner = static function (Context $context, string $message) use ($providerFactory, $toolRegistry, $messageRepository, $options): iterable {
            $apiKey = $options->get_api_key();
            if ($apiKey === '') {
                yield [
                    'type' => 'error',
                    'code' => 'missing_api_key',
                    'message' => 'OpenRouter API key is not configured.',
                ];
                yield [
                    'type' => 'done',
                    'stop_reason' => 'error',
                    'total_iterations' => 0,
                ];
                return;
            }

            $provider = $providerFactory->make('openrouter', [
                'api_key' => $apiKey,
                'endpoint' => (string) get_option('wpclaw_openrouter_endpoint', 'https://openrouter.ai/api/v1/chat/completions'),
                'context_windows' => $options->get_context_windows(),
            ]);

            $maxIterations = $options->get_max_iterations();

            $loop = new Loop(
                $provider,
                $toolRegistry,
                $messageRepository,
                $maxIterations,
                CancelSignal::for_session($context->session_id())
            );

            yield from $loop->run($context, $message);
        };

        $this->chatEndpoint = $chatEndpoint ?? new ChatEndpoint(
            $sessionRepository,
            $guard,
            $roleGate,
            $runner,
            null,
            new RateLimiter(),
            $options,
            new InputSanitizer()
        );

        $this->historyEndpoint = $historyEndpoint ?? new HistoryEndpoint(
            $sessionRepository,
            $messageRepository,
            $guard
        );

        $this->cancelEndpoint = $cancelEndpoint ?? new CancelEndpoint(
            $sessionRepository,
            $guard
        );
    }

    public function register_routes(): void
    {
        register_rest_route(
            'wpclaw/v1',
            '/hello',
            [
                'methods' => 'GET',
                'permission_callback' => [$this, 'can_access'],
                'callback' => [$this, 'hello'],
            ]
        );

        register_rest_route(
            'wpclaw/v1',
            '/chat',
            [
                'methods' => 'POST',
                'permission_callback' => [$this, 'can_access'],
                'callback' => [$this, 'chat'],
            ]
        );

        register_rest_route(
            'wpclaw/v1',
            '/history',
            [
                [
                    'methods' => 'GET',
                    'permission_callback' => [$this, 'can_access'],
                    'callback' => [$this, 'history_get'],
                ],
                [
                    'methods' => 'DELETE',
                    'permission_callback' => [$this, 'can_access'],
                    'callback' => [$this, 'history_delete'],
                ],
            ]
        );

        register_rest_route(
            'wpclaw/v1',
            '/chat/cancel',
            [
                'methods' => 'POST',
                'permission_callback' => [$this, 'can_access'],
                'callback' => [$this, 'chat_cancel'],
            ]
        );
    }

    public function can_access(): bool
    {
        return is_user_logged_in();
    }

    /**
     * @return mixed
     */
    public function hello(mixed $request): mixed
    {
        return $this->respond(
            [
                'ok' => true,
                'message' => 'WPClaw bootstrap route is working.',
                'user_id' => get_current_user_id(),
            ],
            200
        );
    }

    /**
     * @return mixed
     */
    public function chat(mixed $request): mixed
    {
        $result = $this->chatEndpoint->handle($request);

        return $this->respond($result, $this->status_from_result($result));
    }

    /**
     * @return mixed
     */
    public function history_get(mixed $request): mixed
    {
        $result = $this->historyEndpoint->get($request);

        return $this->respond($result, $this->status_from_result($result));
    }

    /**
     * @return mixed
     */
    public function history_delete(mixed $request): mixed
    {
        $result = $this->historyEndpoint->delete($request);

        return $this->respond($result, $this->status_from_result($result));
    }

    /**
     * @return mixed
     */
    public function chat_cancel(mixed $request): mixed
    {
        $result = $this->cancelEndpoint->handle($request);

        return $this->respond($result, $this->status_from_result($result));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function status_from_result(array $result): int
    {
        if (($result['ok'] ?? false) === true) {
            return 200;
        }

        if (isset($result['error']) && is_array($result['error']) && isset($result['error']['status'])) {
            return (int) $result['error']['status'];
        }

        return 400;
    }

    /**
     * @param array<string, mixed> $payload
     * @return mixed
     */
    private function respond(array $payload, int $status): mixed
    {
        if (class_exists('WP_REST_Response')) {
            return new \WP_REST_Response($payload, $status);
        }

        $payload['_status'] = $status;

        return $payload;
    }
}
