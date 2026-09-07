# Editing Guide & Code Modification Rules

## 1. Code Standards
- **PHP:** Follow PSR-12 and WordPress Coding Standards (WPCS). Strict typing enabled (`declare(strict_types=1);`) where applicable.
- **JavaScript:** ES6+ standards, modular structure, strict linting.
- **CSS:** BEM naming convention prefixed with `.gca-` (e.g. `.gca-widget__container`, `.gca-message--user`).

## 2. Documentation Synchronization Rule
Before merging or implementing any change:
1. Check if the change impacts REST endpoints -> Update `API_CONTRACT.md` & `SCHEMA_INVENTORY.md`.
2. Check if the change alters database structure -> Update `DB_SHAPES.md`.
3. Check if new classes or components are introduced -> Update `COMPONENTS.md`, `COMPONENT_INVENTORY.md`, and `PLUGIN_TREE.txt`.
4. Log all significant modifications into `CHANGELOG.md`.
5. Keep `SECURITY.md` updated if security boundaries or encryption routines change.
