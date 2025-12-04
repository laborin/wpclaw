# Providers

## Current provider stack
- `ProviderFactory`
- `OpenRouterProvider`
- `CurlStreamer`
- `StreamParser`

## Responsibilities
- `OpenRouterProvider` maps internal messages/tool schemas to upstream payload format.
- `CurlStreamer` performs HTTP streaming transport.
- `StreamParser` parses SSE chunks into normalized events.
- Factory chooses provider by model/options.

## Event contract into loop
Provider emits normalized pieces consumed by `Agent\\Loop`:
- assistant delta text
- tool call start
- tool call result
- done/stop metadata

Loop is intentionally decoupled from transport details.

## Failure handling
- Invalid stream chunks are ignored or converted to controlled error events.
- HTTP/network/provider failures are propagated with stable error payload.
- Cancellation signal is checked between iterations.

## Add another provider
1. Implement provider class that returns normalized stream events.
2. Wire it in `ProviderFactory` with model/provider selector.
3. Run frontend build and a manual chat smoke test.
4. Add automated coverage later when the prototype test plan is started.
