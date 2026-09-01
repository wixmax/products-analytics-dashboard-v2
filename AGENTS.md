## Clean Modular Architecture & Project Standards

This project enforces strict clean architecture principles:
1. **Modular Controllers (< 400 lines):** Never bloat controllers (e.g. `Products.php`, `McpController.php`). Group domain endpoints into dedicated Traits under `app/Controllers/Traits/` (e.g. `SavedAdsTrait`, `SnapshotTrait`, `AiAnalysisTrait`, `VectorizeTrait`, `SyncTrait`, `SettingsTrait`).
2. **MCP Tool Subsystem:** All MCP tools must implement `App\Libraries\Mcp\ToolInterface` and be registered in `App\Libraries\Mcp\ToolRegistry`. Keep dynamic prompts in `App\Libraries\Ai\PromptBuilder.php`.
3. **Multi-Tenancy & Quotas:** Always scope user data using `App\Libraries\TenantContext` / `App\Models\TenantModel` and check usage via `App\Libraries\SaaS\QuotaManager`.
4. **Data Compression & Async Jobs:** Use `App\Libraries\Storage\SnapshotStorageHelper` for Gzip `raw_json` compression. Run heavy CLI operations via `App\Libraries\Queue\BackgroundTaskRunner` or `php spark task:async`.
5. **View Modularity & Shared JS:** Extract shared modals into `app/Views/partials/` and centralized JS in `public/`.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, invoke the `skill` tool with `skill: "graphify"` before doing anything else.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).

## Safe Git Push Policy (No Automatic Push)
- **Strictly FORBIDDEN to execute `git push` or upload code to remote repositories (GitHub/origin) automatically.**
- All modifications, tests, and commits must remain LOCAL.
- NEVER run `git push` unless the user explicitly commands it in their message (e.g. "ارفع التعديلات", "ارفع المشروع", "push to origin").
- When finishing a task, only notify the user that local changes are complete and tested, and wait for their explicit push instruction.
