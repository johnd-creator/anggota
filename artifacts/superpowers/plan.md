# Plan: Final Mobile Polish (Data Cards, CSP & Login)

## Goal
Complete the Mobile UX transformation by implementing the missing pieces (Data Cards), fixing the Preview CSP bug, and polishing the Mobile Login screen to look "branded" rather than plain white.

## Assumptions
- "Mobile" < 768px.
- Login background image for mobile header can reuse the "abstract" red background from desktop but resized/cropped, or we use a solid brand color with the white Logo.

## Plan

### 1. Fix CSP for Preview Logo
Allow `blob:` images so Dynamic/Object URLs work (often used for previews or local component assets).
- **Files:** `app/Http/Middleware/SecurityHeadersMiddleware.php`
- **Change:** Add `blob:` to `img-src` directive.
- **Verify:** Refresh Letter Preview page -> Logo should appear.

### 2. Polish Mobile Login UI
Make it look premium and branded, not just a white form.
- **Files:** `resources/js/Pages/Auth/Login.vue`
- **Change:**
    -   Add a **Blue/Red Header** block (h-48) at the top with the White Logo centered.
    -   Move the form into a white **Card** that overlaps the header (negative margin-top).
    -   Add "Selamat Datang" text inside the card.
-   **Visual Reference:** Similar to many banking/fintech apps (Header color + Overlapping Card).
- **Verify:** Check mobile login view -> looks "Official".

## Risks & mitigations
- **Risk:** Overlapping layout might break on very small SE/iPhone 5 screens.
    -   **Mitigation:** Use safe padding and `min-height`.

## Rollback plan
- Revert changes to `SecurityHeadersMiddleware.php`, `Login.vue`, `Dashboard.vue`.
