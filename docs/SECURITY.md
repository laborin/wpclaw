# Security

## Access control
- REST endpoints require authenticated users.
- `RoleGate` controls who can chat and who can execute tools.
- Blocks can hide output when role is disallowed (`hideIfDisallowed`).

## Request protection
- WordPress REST nonce is required for state changing calls.
- `InputSanitizer` normalizes inbound request fields.
- `RateLimiter` limits request burst per user/session.

## Tool safety
- Requested tool names are checked against `Registry`.
- Global settings are the maximum allowed tool list.
- Block tool settings can only reduce the global allowed list.
- Tool execution checks capabilities before `execute`.
- Tool execution denies calls that are not enabled for current context.
- Schema validation rejects invalid tool arguments early.

## Secrets
- API keys are read through `KeyVault` and not exposed by REST endpoints.
- Settings page should be restricted to admin users only.

## Persistence and privacy
- Session history is user-scoped in repositories.
- History clear endpoint only affects current user scope.
- No public endpoint expose private chat history.

## Remaining hardening
- Add configurable max message length in settings UI.
- Add audit logging hooks for tool execution failures.
- Add optional redaction for sensitive fields in stored messages.
