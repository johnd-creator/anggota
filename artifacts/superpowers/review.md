# Superpowers Review

## Blockers
*None identified at this time.* The application appears to have comprehensive test coverage and follows secure coding practices (policy checks, sanitization).

## Majors
*   **Fat Controller (`LetterController`)**: The `LetterController` is over 1000 lines and handles multiple distinct responsibilities:
    *   Listing logic (Inbox, Outbox, Approvals)
    *   CRUD operations
    *   File handling (Attachments)
    *   PDF Generation (using `Dompdf` directly)
    *   QR Code Generation
    *   Template Rendering
    *   Verify logic
    **Recommendation**: Refactor into smaller Single Action Controllers (e.g., `Letter/InboxController`, `Letter/ApprovalController`) or extract logic into dedicated services (e.g., `LetterPdfService`, `LetterQrService`).
*   **Logic in Routes (`web.php`)**: There are substantial logic blocks inside route closures, particularly for `/reports/{type}/export` (Lines 104-168) and members export (Lines 299-332).
    **Why**: This breaks Laravel's route caching (`php artisan route:cache`) and makes testing harder.
    **Recommendation**: Move this logic to `ReportsExportController` or a dedicated `ExportController`.

## Minors
*   **Inconsistent Route Definitions**: `web.php` mixes `Route::controller()->group(...)` with individual `[Controller::class, 'method']` arrays.
    **Recommendation**: Standardize on `Route::controller()` for groups to improve readability.
*   **Direct Library Usage in Controller**: `LetterController` instantiates `\Dompdf\Dompdf` directly (Line 623).
    **Recommendation**: Use a wrapper service or a simpler interface to decouple the library dependency.
*   **Missing API Resources**: The `LetterController` returns `Inertia::render` with array data that mimics resources but isn't formalized.
    **Recommendation**: Use Laravel's API Resources (`JsonResource`) even for Inertia props to ensure consistent data shaping and transformation.

## Nits
*   **Build Configuration**: `vite.config.js` sets `chunkSizeWarningLimit: 800`. While this suppresses the warning, it doesn't fix the underlying large bundle size. Consider dynamic imports for heavy components (e.g., TipTap editor).
*   **Strict Typing**: Some methods lack return type hints (e.g., `inbox(Request $request)`). Adding `: Response` would be better.
*   **Magic Strings**: Status strings ('submitted', 'approved', etc.) are scattered. These should be in an Enum (e.g., `LetterStatus`).

## Summary & Next Actions
The codebase is generally healthy with excellent test coverage. The primary "technical debt" is the growing size of `LetterController` and the presence of business logic in `routes/web.php`.

**Proposed Actions:**
1.  **Refactor Routes**: Move the export logic from closures in `web.php` to `ReportsExportController`.
2.  **Refactor `LetterController`**: Extract PDF and QR logic into services if not already fully utilized, or split the controller if it continues to grow.
3.  **Optimize Build**: Investigate dynamic import for the Rich Text Editor to improve `npm run build` performance.
