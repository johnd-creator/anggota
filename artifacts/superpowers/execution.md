# Execution Notes

### Phase 1: Mobile Data Cards (UX)
- **Create DataCard Component**: ✅ Created `resources/js/Components/Mobile/DataCard.vue`
- **Implement Data Cards on Dashboard**: ✅ Modified `Dashboard.vue` to show DataCards for `recent_activities` on mobile.
- **Implement Data Cards on Inbox**: ✅ Modified `Inbox.vue` to show DataCards for `letters` on mobile.

### Phase 2: Route Refactor (Maintainability)
- **Create SettingsController**: ✅ Created `SettingsController.php` with methods:
    - `index`, `updateNotifications`, `updateProfile`, `updatePassword`, `getSessions`.
- **Update Settings Routes**: ✅ Refactored `web.php` to use `SettingsController`.
- **Create AuditLogController**: ✅ Created `Admin/AuditLogController.php` with `index` method.
- **Update Audit Routes**: ✅ Refactored `web.php` to use `AuditLogController`.

### Notifications Mobile View
- **Mobile Filter Bar**: ✅ Updated `Notifications/Index.vue` to separate mobile (stacked) and desktop (flex) views for filters. Made tabs scrollable on x-axis.
- **Mobile Data Cards**: ✅ Replaced the standard list with `DataCard.vue` on mobile view.
    - Status badge logic implemented in `DataCard` props.
    - "Toggle Read" action added to card footer.

### Minors & Nits Fixes
- **Letter Filtering Refactor**: ✅ Added `scopeVisibleTo` and `scopeFilterByRequest` to `Letter` model. Refactored `LetterController::inbox` and `approvals` to use these scopes, removing duplicate logic.
- **CSS Styles**: ✅ Replaced inline colors in `AppLayout.vue` with `bg-sidebar-bg` and `border-sidebar-border` using existing `tailwind.config.js` theme.
- **CSP Cleanup**: ✅ Refactored `SecurityHeadersMiddleware` to cleanly separate Dev vs Prod CSP rules and use semantic variable names.
- **Nits**: 
    - ✅ Removed unnecessary `Schema::hasTable` checks in `DashboardController`.
    - ✅ Switched to PHP 8 nullsafe operator (`?->`) in `DashboardController` and `SettingsController`.
    - ✅ Removed outdated "inline styles" comments from `AppLayout.vue`.
