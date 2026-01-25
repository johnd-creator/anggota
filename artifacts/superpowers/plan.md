# Plan: Minors & Nits Fixes

## Goal
Clean up technical debt identified in the Codebase Review (4 Minors, 3 Nits).

## Assumptions
- Code logic checks for `Letter` filtering will be moved to Model Scopes.
- Brand colors are `#1A2B63` (Primary) and `#2E4080` (Secondary/Border) — will add to Tailwind config.

## Plan

### Minor 1 & 2: Letter Filtering & Query Styles
Standardize filtering in `LetterController` using Model Scopes.
- **Files:** `app/Models/Letter.php`, `app/Http/Controllers/LetterController.php`
- **Change:**
    - Method: Add `scopeVisibleTo($query, User $user)` to `Letter` model. This scope will encapsulate the role-based logic (Anggota vs Admin Unit vs Admin Pusat).
    - Method: Add `scopeFilterByRequest($query, Request $request)` to encapsulate search, status, and category filters.
    - Update `LetterController::inbox`, `outbox`, `approvals` to use these scopes.
- **Verify:** Run feature tests (`LetterInboxTest` if exists, or manually check Inbox/Approvals).

### Minor 3: Hardcoded Inline Styles
Replace sidebar inline styles with Tailwind classes.
- **Files:** `tailwind.config.js`, `resources/js/Layouts/AppLayout.vue`
- **Change:**
    - Add `brand-sidebar: '#1A2B63'` and `brand-sidebar-border: '#2E4080'` to `tailwind.config.js`.
    - Update `AppLayout.vue` to use `bg-brand-sidebar` and `border-brand-sidebar-border`.
- **Verify:** `npm run build` and visual check (sidebar color remains same).

### Minor 4: CSP Configuration
Move CSP logic to a cleaner configuration or service.
- **Files:** `app/Http/Middleware/SecurityHeadersMiddleware.php`
- **Change:** Use `config('app.debug')` as the primary flag and possibly move host lists to `config/cors.php` or `config/security.php` if needed. For now, just ensuring strict `app()->isLocal()` usage and strictly splitting dev vs prod headers logic.
- **Verify:** Visit site, check Response Headers -> CSP should be correct.

### Nits
1.  **Schema Checks**: Remove `Schema::hasTable` in `DashboardController` (assumed location based on review, will check).
2.  **Optional Helper**: Replace `optional($foo)->bar` with `$foo?->bar` in `SettingsController` and others.
3.  **Comments**: Remove "Menu item styling" noise.

## Risks & mitigations
- **Risk:** Scope refactor might miss a specific "OR" condition for a role.
    - **Mitigation:** I will strictly copy the existing logic into the Scope first, ensuring parity.

## Rollback plan
- Revert changes to `Letter.php` and `LetterController.php`.
