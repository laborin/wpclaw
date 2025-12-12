# WPClaw

Experimental WordPress plugin with two native chat blocks (React and Interactivity) that share one backend agent loop and tool system.

This project is an early prototype. The core runtime, settings page, and block UI are available, but the public API and internal architecture may still change.

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

Release ZIPs include the compiled block assets in `blocks/*/build`, so users do not need Node.js.

## Local development
1. `npm install`
2. Build block assets: `npm run build`
3. Activate the plugin in WordPress.

## Development commands
- Build all blocks: `npm run build`
- Watch React block: `npm run start:chat-react`
- Watch Interactivity block: `npm run start:chat-interactivity`
- JS lint: `npm run lint:js`
