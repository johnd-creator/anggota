# Brainstorm: Completing the Mobile Experience

## Goal
Finalize the Mobile UX implementation by addressing the identified "missing pieces" and fixing specific bugs found during testing (Preview Logo CSP).

## Constraints
- **Security:** CSP is essential; we cannot just remove it. We must allow specific valid sources.
- **Scope:** Mobile Layout, Mobile Login, Mobile Data Tables (Cards), and now fixing Preview issues.

## Known context
- **Mobile Navigation:** `BottomNav.vue` implemented (needs verification).
- **Mobile Layout:** `MobileLayout.vue` implemented (needs verification).
- **Login:** Updated (needs verification).
- **Data Tables:** `DataCard` and implementation in Dashboard/Inbox **PENDING** (not yet executed).
- **CSP Issue:** User provided screenshot showing blocked images in `Preview.vue`.
    - Error: `Violates Content Security Policy directive: "img-src 'self' data: ..."`
    - The code uses `new URL(...).href` for default logo which might resolve to `blob:` or be treated strangely in dev mode.
    - Also, user input could come from external storage (S3/Minio) not yet whitelisted? (Current middleware only has `ui-avatars`, `google`, `self`, `data:`).

## Risks
- **Incomplete Mobile Flow:** We did Navigation + Login, but **Data Cards** (the big "Excel" fix) are still just a plan, not code.
- **CSP Fragility:** Whitelisting `*` is bad. We need to find *exactly* why the logo is blocked.

## Options

### Option 1: Fix CSP (Acceptance Criteria 1)
Modify `SecurityHeadersMiddleware.php`.
- Adding `blob:` to `img-src` (often needed for `URL.createObjectURL` or Vite assets in some modes).
- Adding the Vite dev server URL explicitly if not covered? (It is covered: `http://127.0.0.1:5173`).
- **Hypothesis:** The logo might be loaded from a different port or protocol in the screenshot context? Or `blob:`.

### Option 2: Implement Missing Data Cards (Acceptance Criteria 2)
The user asked "apakah ada yang kurang?". **YES!** We planned the Data Cards but stopped after Step 2 (Integration). We haven't built `DataCard.vue` or updated `Dashboard.vue` yet.
-   **Action:** Continue the execution plan to build `DataCard.vue`.

## Recommendation
1.  **Fix CSP:** Add `blob:` to `img-src` in `SecurityHeadersMiddleware.php`.
2.  **Continue Execution:** Proceed to **Step 4** of the original plan (Data Cards). This is the biggest missing piece for a "complete" mobile experience.

## Acceptance criteria
- [ ] Update `SecurityHeadersMiddleware.php` to include `blob:` in `img-src`.
- [ ] Create `DataCard.vue`.
- [ ] Implement `DataCard` in `Dashboard.vue` and `Letters/Inbox.vue`.
