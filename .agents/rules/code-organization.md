# Clean Architecture & Code Organization Rules

## Modularity & Component Reusability
1. **Never duplicate HTML modals:** All popup modals (product details, help modals, info breakdown, AI history drawers) must be loaded from `app/Views/partials/product-modals.php` via `<?= $this->include('partials/product-modals') ?>`.
2. **Never duplicate shared JS functions:** Core modal controllers (`openDetailsModal`, `openIndexInfoModal`, `initVideoJs`, `toggleStoreListAction`, `downloadProductMedia`, `downloadProductDataJSON`, `generateSimulatedActivity`, etc.) must be loaded from `public/product-modal-core.js` and `public/analysis-helper.js`.
3. **Follow DRY across all views:** Before adding or modifying page features, check if existing components or partials can be extended rather than copy-pasted.
4. **Update Graphify:** Run `graphify update .` after updating code to keep the architecture knowledge graph synchronized.
