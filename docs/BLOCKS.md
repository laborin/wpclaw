# Blocks

## Block variants
- `wpclaw/chat-react`
- `wpclaw/chat-interactivity`

Both blocks are dynamic. Server renderers enforce role checks and output data attributes used by frontend script.

## Shared block attributes
- `placeholder`
- `hideIfDisallowed`
- `theme`
- `maxHeight`

## Runtime behavior
- Loads history using `GET /history`.
- Sends user message using `POST /chat`.
- Shows tool calls/results emitted by loop events.
- Supports cancel run (`POST /chat/cancel`) and clear history (`DELETE /history`).

## Agent settings
System prompt additions and enabled tools are configured globally in plugin settings. Blocks only control presentation and access display behavior.

## Build commands
- `npm run build` builds both block variants.
- `npm run start:chat-react` starts watch mode for React block.
- `npm run start:chat-interactivity` starts watch mode for Interactivity block.

Each block compiles to its own folder:
- `blocks/chat-react/build/*`
- `blocks/chat-interactivity/build/*`

This avoids accidental cleanup of sibling folders.

## Manual test checklist
1. Insert both blocks in a page while logged as allowed role.
2. Confirm history loads and previous messages are shown.
3. Send message and confirm assistant output is rendered.
4. Ask action that triggers tools and confirm tool panel updates.
5. Press cancel during long run and confirm run stops.
6. Clear history and confirm UI resets.
