# WPClaw

WPClaw is an experimental WordPress-native AI agent. It runs inside WordPress, uses the site as context, and can interact with approved tools through the same permission model that already protects the admin.

This prototype is evolving from a simple chat experiment into an OpenClaw-style agent runtime for WordPress. The current build includes chat blocks, a backend agent loop, tool execution, session history, and provider settings.

## What is implemented
- session and message persistence (`wpclaw_sessions`, `wpclaw_messages`)
- REST endpoints: `/hello`, `/chat`, `/history`, `/chat/cancel`
- provider layer with streaming parser and cancellation support
- tool registry + schema validation + capability checks
- settings page for provider, model, system prompt, role, and tool controls
- dynamic Gutenberg blocks:
  - `wpclaw/chat-react`
  - `wpclaw/chat-interactivity`

## Install from a release ZIP
1. Download the ZIP from a tagged release.
2. Upload it in WordPress under Plugins > Add New > Upload Plugin.
3. Activate WPClaw.

## Local development
1. `npm install`
2. Build block assets: `npm run build`
3. Activate the plugin in WordPress.

## Development commands
- Build all blocks: `npm run build`
- Watch React block: `npm run start:chat-react`
- Watch Interactivity block: `npm run start:chat-interactivity`
- JS lint: `npm run lint:js`
