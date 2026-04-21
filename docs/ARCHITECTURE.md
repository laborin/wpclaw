# Architecture

## Goal
Plugin provides 2 Gutenberg chat blocks that call one shared REST backend with an agent loop, tool execution, and per-user session storage.

## Main layers
- Bootstrap: `wpclaw.php`
- Composition root: `WPClaw\\Plugin`
- Settings and admin UI: `WPClaw\\Settings\\*`
- REST endpoints: `WPClaw\\Rest\\*`
- Agent runtime: `WPClaw\\Agent\\*`
- Provider and streaming: `WPClaw\\Provider\\*`
- Tool system: `WPClaw\\Tools\\*`
- Session persistence: `WPClaw\\Session\\*`
- Security primitives: `WPClaw\\Security\\*`

## Request flow
1. Frontend block sends `POST /wpclaw/v1/chat`.
2. `Guard` validates auth, nonce, and permission callback.
3. `InputSanitizer` and `RateLimiter` validate request policy.
4. `ChatEndpoint` loads recent conversation from repositories.
5. `ChatEndpoint` resolves global tool settings and the composed system prompt.
6. `Loop` calls provider and executes tools through `Registry`.
7. Loop emits normalized events (`assistant_delta`, `tool_call_start`, `tool_call_result`, `done`).
8. Endpoint stores assistant/tool messages and returns event list.

## Persistence model
- `wpclaw_sessions`: one row per user session.
- `wpclaw_messages`: ordered chat history, tool metadata, timestamps.

Session and message repositories use injected `wpdb` so they stay testable and easy to replace.

## Extensibility points
- Add tools by implementing `AbstractTool` and registering in `Registry`.
- Add providers through `ProviderFactory`.
- Add block variants while keeping same REST contract.

## Design notes
- Backend is provider-agnostic and do not depends on OpenRouter only.
- Frontend variants stay isolated but share same API client and payload schema.
- Tool execution is capability-gated, so disallowed tools never run even if requested by model.
