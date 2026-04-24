# WPClaw

WPClaw is an experimental WordPress-native AI agent. It runs inside WordPress, uses the site as context, and can interact with approved tools through the same permission model that already protects the admin.

This prototype is evolving from a simple chat experiment into an OpenClaw-style agent runtime for WordPress. The current build includes chat blocks, a backend agent loop, tool execution, session history, and provider settings.

## What is implemented
- session and message persistence (`wpclaw_sessions`, `wpclaw_messages`)
- REST endpoints: `/hello`, `/chat`, `/chat/stream`, `/history`, `/chat/cancel`
- provider layer with streaming parser and cancellation support
- tool registry + schema validation + capability checks
- settings page for provider, model, system prompt, role, and tool controls
- dynamic Gutenberg block: `wpclaw/chat-react`
- streaming responses

## To-do
- Multi-agent support
- Support for basic OpenClaw-style identity and memory files, database backed
- Telegram bot support
- A more complete set of tools

There are two Gutenberg blocks in the source but only the React one is maintained. The old Interactivity API block source remains in the repository as deprecated prototype code. It was an early attempt to play with WordPress Interactivity API.

## Install from a release ZIP
1. Download the `wpclaw-<tag>.zip` asset from a GitHub release.
2. Upload it in WordPress under Plugins > Add New > Upload Plugin.
3. Activate WPClaw.

## Local development
1. `npm install`
2. Build block assets: `npm run build`
3. Activate the plugin in WordPress.

## Development commands
- Build all blocks: `npm run build`
- Watch React block: `npm run start:chat-react`
- Watch deprecated Interactivity block: `npm run start:chat-interactivity`
- JS lint: `npm run lint:js`
- Bump version: `npm run version:bump -- 0.2.0`

## Releases
Version tags (`v*`) create GitHub releases automatically. The release workflow installs dependencies, builds the block assets, packages the plugin as `wpclaw-<tag>.zip`, and uploads that ZIP as the release asset.

Before creating a tag, run `npm run version:bump -- <version>`, commit the version change, then tag that commit as `v<version>`.
