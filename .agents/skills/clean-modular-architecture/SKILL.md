---
name: clean-modular-architecture
description: >-
  Enforce clean architecture, DRY (Don't Repeat Yourself) principle, modular view partials,
  and centralized JavaScript/PHP services to prevent duplicate code and ensure high maintainability.
---

# Clean Modular Architecture & Code Organization Guidelines

This skill guides the agent in maintaining a clean, modular, and non-repetitive codebase.

## Core Directives

### 1. The DRY Principle (Don't Repeat Yourself)
* **Check before creating:** Always search the codebase for existing partials, components, helper functions, and shared libraries before creating new implementations.
* **Refactor on discovery:** If you identify repetitive HTML structures, modals, card layouts, or JavaScript functions across 2 or more pages, extract them into a shared component.

### 2. View Modularity (CodeIgniter Views & Partials)
* **Shared UI Modals:** All popup modals (e.g., product details, help guides, info cards, drawers) must live in `app/Views/partials/` (such as `app/Views/partials/product-modals.php`) and be included via:
  ```php
  <?= $this->include('partials/product-modals') ?>
  ```
* **Shared UI Elements:** Reusable navigation, headers, filters, and cards should be split into dedicated partials rather than duplicated in full page templates.

### 3. JavaScript Centralization
* **Central Core Modules:** Core UI logic, modal controllers, media players (`video.js`), and helper scripts should reside in dedicated modules in `public/` (e.g., `product-modal-core.js`, `analysis-helper.js`).
* **Avoid Inline Duplication:** Page-specific scripts should only handle page-specific parameters and delegate standard product interactions (modals, video events, toasts, downloads) to the shared modules.

### 4. Code Maintenance & Verification
* After making UI or structural changes, verify that all consumer views remain functional and free of syntax errors (`php -l`).
* Keep the knowledge graph up to date by running `graphify update .` after code modifications.
