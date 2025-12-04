# Adding Tools

## 1) Create tool class
Create class under `includes/Tools/` extending `AbstractTool`.

Required methods:
- `get_name(): string`
- `get_description(): string`
- `get_schema(): array`
- `get_required_capability(): string`
- `execute(array $arguments, Context $context): ExecutionResult`

## 2) Define input schema
Schema is validated by `SchemaValidator` before execution.
Keep schema small and explicit, avoid loose params.

## 3) Register tool
Add instance to `Registry` in plugin composition.

Current tools:
- `search_posts`
- `get_post`
- `list_recent_comments`
- `get_site_stats`
- `search_media`
- `get_current_user`
- `create_draft_post`
- `update_post`
- `delete_post`

## 4) Capability policy
If tool modifies content, require strict capability (example `edit_posts`).
If tool is read only, use least privilege needed.
Global settings must enable the tool before a block can use it.

## 5) Manual verification
Use chat block and force model to call new tool.
Confirm tool call start/result events are visible and message is persisted.

Automated tests are planned for a later prototype stage.
