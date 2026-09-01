---
name: clean-modular-architecture
description: >-
  Enforce clean modular architecture, DRY principle, modular controller traits,
  standardized MCP tools, Tenant isolation, and centralized async/cache services.
---

# Clean Modular Architecture & Code Organization Guidelines

This skill guides the AI agent in maintaining a clean, modular, scalable, and non-repetitive codebase for **Products Analytics Dashboard**.

---

## 1. Backend Controller Architecture (Modular Traits)
* **Keep Controllers Thin (< 400 lines):** Never dump hundreds of lines of logic directly into a main controller (e.g. `Products.php`, `McpController.php`).
* **Use Dedicated Modular Traits:** Group related endpoint methods into focused traits inside `app/Controllers/Traits/`:
  - `SnapshotTrait.php`: Snapshot listing, import, restore, deletion.
  - `SavedAdsTrait.php`: Saved items, bookmarks, ratings, notes, collections, watchlist.
  - `AiAnalysisTrait.php`: AI screening, deep dive, strategy generator, history.
  - `VectorizeTrait.php`: Cloudflare Vectorize status, indexing, semantic search.
  - `SyncTrait.php`: tRPC external sync, local availability verification.
  - `SettingsTrait.php`: System settings, database data cleanup, date deletions.
* **Preserve Route Compatibility:** Always ensure new traits plug into the parent controller so that existing routes in `app/Config/Routes.php` and frontend AJAX calls remain 100% compatible.

---

## 2. Model Context Protocol (MCP) & AI Tool Registry
* **Standardized Tools:** All MCP tools must implement `App\Libraries\Mcp\ToolInterface` (`getName()`, `getDescription()`, `getInputSchema()`, `execute()`).
* **Modular Tool Handlers:** Tool implementations live under `app/Libraries/Mcp/Tools/` (e.g., `ProductFilterTool`, `SnapshotTools`, `SavedProductsTool`, `VectorSearchTool`, `FacebookAdsTools`, `DynamicSkillTool`).
* **Central Registry:** All tools are registered and dispatched through `App\Libraries\Mcp\ToolRegistry`, which handles dynamic skills, database-driven tool enabling/disabling, aliases, and per-tenant quotas.
* **Decoupled Prompts:** AI prompt templates and prompt building must live in `App\Libraries\Ai\PromptBuilder.php`, never hardcoded inline in controllers.

---

## 3. Multi-Tenancy & SaaS Isolation
* **Tenant Scoping:** Always respect workspace boundaries using `App\Libraries\TenantContext::getInstance()`.
* **Database Models:** Application models must extend `App\Models\TenantModel` or use `TenantableTrait` to prevent cross-tenant data leaks.
* **Usage Quotas:** Verify and record tenant resource limits (`mcp_calls`, `vector_searches`, `ai_analyses`) using `App\Libraries\SaaS\QuotaManager`.

---

## 4. Performance, Compression & Async Background Tasks
* **Snapshot Compression:** Always use `App\Libraries\Storage\SnapshotStorageHelper` (`compress()` and `decompress()`) when reading or storing `raw_json` in `data_snapshots` to maintain 80% database storage efficiency with backwards compatibility.
* **Fast Response Caching:** Utilize `App\Libraries\Cache\ResponseCache` for computationally heavy queries with TTL and cache key hashing.
* **Non-blocking CLI Tasks:** Heavy operations (e.g. data syncing, bulk vectorize indexing) must be dispatched asynchronously using `App\Libraries\Queue\BackgroundTaskRunner` or Spark command `php spark task:async <command>`.

---

## 5. Frontend & View Modularity
* **Shared UI Modals:** All popup modals live in `app/Views/partials/` (e.g., `app/Views/partials/product-modals.php`) and are included via `<?= $this->include('partials/...') ?>`.
* **Centralized JavaScript:** Shared interactions, modal controllers, and media helpers reside in `public/` (e.g., `product-modal-core.js`, `video.js`).

---

## 6. Verification & Safe Deployment
* **Automated Unit Testing:** Run `.\vendor\bin\phpunit tests\unit\` to ensure 100% test passing before finalizing changes.
* **Syntax Checks:** Verify modified PHP files with `php -l <filepath>`.
* **Knowledge Graph:** Keep the codebase graph current by running `graphify update .` after code modifications.
* **Safe Git Push Policy:** Strictly FORBIDDEN to execute `git push` without explicit user request.
