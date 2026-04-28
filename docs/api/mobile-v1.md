# Mobile API v1

Base path: `/api/mobile/v1`

Flutter clients should send:

```http
Accept: application/json
Authorization: Bearer <access_token>
```

Store `access_token` in secure storage on the device. Do not use the legacy `X-API-Token` endpoints from the public Android app.

## Auth

### `POST /auth/login`

Request:

```json
{
  "email": "anggota@example.com",
  "password": "password",
  "device_name": "android"
}
```

Response:

```json
{
  "access_token": "token-value",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Nama Anggota",
    "email": "anggota@example.com",
    "role": { "id": 4, "name": "anggota", "label": "Anggota" },
    "current_unit_id": 1,
    "member_context_unit_id": 1,
    "member": {}
  }
}
```

### `POST /auth/logout`

Revokes the current bearer token.

### `GET /me`

Returns the authenticated user, role, unit context, and linked member summary.

## Utility

### `GET /config`

Returns mobile API metadata, upload limits, and dues defaults.

### `GET /features`

Returns enabled server feature flags for announcements, letters, finance, and reports.

### `GET /meta/lookups`

Returns scoped lookup data for organization units, union positions, aspiration categories, letter categories, supported statuses, document types, and notification categories. Non-global users only receive their current unit.

### `GET /dashboard`

Returns the authenticated user's mobile dashboard summary: member profile summary, current dues status, unread notification count, latest scoped aspirations, latest visible letters, and pinned announcements.

## Member Features

### `GET /profile`

Returns the authenticated user's own member profile and latest update requests. Users without a linked member receive `member: null`.

### `PATCH /profile/update-request`

Allowed fields:

```json
{
  "address": "Alamat baru",
  "phone": "081234567890",
  "emergency_contact": "081298765432",
  "company_join_date": "2025-01-01",
  "notes": "Catatan opsional"
}
```

The API creates or replaces the user's pending update request.

### `POST /profile/photo`

Multipart form fields:

- `photo`: `jpg`, `jpeg`, `png`, or `webp`, max 5 MB.

### `DELETE /profile/photo`

Deletes the authenticated member's current profile photo if one exists.

### `POST /profile/documents`

Multipart form fields:

- `type`: `surat_pernyataan` or `ktp`
- `file`: `pdf`, max 2 MB.

### `GET /member/card`

Returns the authenticated member's card payload, including KTA/NRA, status, unit, QR token, verification URLs, `download_url`, `has_qr`, and `can_download_pdf`.

If `qr_token` or `valid_until` is empty, the API issues them automatically when the member has a KTA and the member unit can issue KTA.

### `GET /member/card/qr`

Returns the authenticated member card QR image as `image/png` or `image/svg+xml`.

### `GET /member/card/pdf`

Downloads the authenticated member's KTA Digital as an A6 PDF. Flutter should call this endpoint with the same bearer token and save the binary response locally.

### `GET /member/card/verify/{token}`

Public JSON verification endpoint for QR scan. Returns only safe card verification fields: member name, unit, status, validity date, and scan timestamp.

### `POST /member/data/export-request`

Records a mobile data export request for the authenticated user.

### `POST /member/data/delete-request`

Records a mobile data deletion request for the authenticated user.

### `GET /dues`

Returns the authenticated member's last 12 dues periods plus a summary.

## Settings

### `PATCH /settings/profile`

Request:

```json
{ "name": "Nama Baru" }
```

Updates the authenticated user's display name and linked member name.

### `PATCH /settings/password`

Request:

```json
{
  "current_password": "old-password",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

Updates the password and revokes other mobile bearer tokens.

### `GET /settings/sessions`

Returns the authenticated user's active mobile bearer tokens.

### `POST /settings/sessions/revoke-others`

Revokes all mobile bearer tokens except the current request token.

### `PATCH /settings/notifications`

Updates notification channel preferences and daily digest preference.

## Notifications

### `GET /notifications`

Returns paginated notifications for the authenticated user only.

Optional query:

- `per_page`: default `15`.

### `POST /notifications/{id}/read`

Marks one owned notification as read.

### `POST /notifications/read-all`

Marks all authenticated-user notifications as read.

### `POST /notifications/{id}/unread`

Marks one owned notification as unread.

### `POST /notifications/read-batch`

Request:

```json
{ "ids": ["uuid-1", "uuid-2"] }
```

Marks owned notifications from the request list as read.

### `GET /notifications/recent`

Returns the latest five authenticated-user notifications.

## Aspirations

### `GET /aspirations`

Returns aspirations scoped to the authenticated user's member-context unit.

Optional query:

- `category`
- `status`
- `sort`: `latest` or `popular`
- `per_page`: default `10`

### `POST /aspirations`

Request:

```json
{
  "category_id": 1,
  "title": "Lampu ruang rapat",
  "body": "Mohon lampu ruang rapat unit diganti karena redup.",
  "tags": ["fasilitas", "rapat"],
  "is_anonymous": false
}
```

### `GET /aspirations/{id}`

Returns one visible aspiration with category, tags, support status, ownership status, and creator visibility based on policy.

### `POST /aspirations/{id}/support`

Adds authenticated member support to one visible aspiration.

### `DELETE /aspirations/{id}/support`

Removes authenticated member support from one visible aspiration.

### `GET /aspiration-categories`

Returns available aspiration categories.

### `GET /aspiration-tags`

Returns available aspiration tag names.

## Announcements

### `GET /announcements`

Returns active announcements visible to the authenticated user and not dismissed by that user.

### `GET /announcements/{id}`

Returns one visible announcement.

### `POST /announcements/{id}/dismiss`

Dismisses one visible announcement for the authenticated user.

### `GET /announcements/attachments/{id}/download`

Downloads an attachment if the authenticated user can view the parent announcement.

## Feedback

### `POST /feedback`

Request:

```json
{
  "rating": 5,
  "message": "Catatan opsional"
}
```

Records mobile app feedback in the activity log.

## Letters

Mobile letters use the existing `LetterPolicy` and authenticated user scope.

- `GET /letters/inbox`
- `GET /letters/outbox`
- `GET /letters/approvals`
- `GET /letters/{id}`
- `GET /letters/{id}/preview`
- `GET /letters/{id}/pdf`
- `GET /letters/{id}/qr`
- `POST /letters`
- `PUT /letters/{id}`
- `DELETE /letters/{id}`
- `POST /letters/{id}/submit`
- `POST /letters/{id}/send`
- `POST /letters/{id}/archive`
- `POST /letters/{id}/approve`
- `POST /letters/{id}/revise`
- `POST /letters/{id}/reject`
- `POST /letters/{id}/attachments`
- `GET /letters/{id}/attachments/{attachment_id}/download`
- `GET /letters/categories`
- `GET /letters/approvers`
- `GET /members/search`
- `GET /letters/template-render`

## Admin Workflows

All endpoints are role/policy gated and non-global users are scoped to their active unit.

- `GET /admin/members`
- `POST /admin/members`
- `GET /admin/members/search`
- `POST /admin/members/export-request`
- `GET /admin/members/{id}`
- `PUT /admin/members/{id}`
- `GET /admin/onboarding`
- `POST /admin/onboarding/{id}/approve`
- `POST /admin/onboarding/{id}/reject`
- `GET /admin/updates`
- `POST /admin/updates/{id}/approve`
- `POST /admin/updates/{id}/reject`
- `GET /admin/mutations`
- `POST /admin/mutations`
- `GET /admin/mutations/{id}`
- `POST /admin/mutations/{id}/approve`
- `POST /admin/mutations/{id}/reject`
- `POST /admin/mutations/{id}/cancel`

## Finance And Reports

- `GET /finance/categories`
- `POST /finance/categories`
- `PUT /finance/categories/{id}`
- `DELETE /finance/categories/{id}`
- `GET /finance/ledgers`
- `POST /finance/ledgers`
- `PUT /finance/ledgers/{id}`
- `DELETE /finance/ledgers/{id}`
- `POST /finance/ledgers/{id}/approve`
- `POST /finance/ledgers/{id}/reject`
- `POST /finance/ledgers/export`
- `GET /finance/dues`
- `PATCH /finance/dues/{id}`
- `PATCH /finance/dues/mass-update`
- `GET /finance/dues/dashboard`
- `GET /reports/growth`
- `GET /reports/mutations`
- `GET /reports/members`
- `GET /reports/aspirations`
- `GET /reports/dues`
- `GET /reports/finance`
- `GET /reports/export`
- `GET /reports/export/status/{id}`

`/reports/export` and finance export currently return queued JSON metadata for Flutter polling; binary export generation remains server-side worker work.

## Master Data And Admin Ops

- `GET /master/units`
- `GET /master/union-positions`
- `GET /master/aspiration-categories`
- `GET /master/letter-categories`
- `GET /master/letter-approvers`
- `GET /admin/roles`
- `POST /admin/roles/{id}/assign`
- `GET /admin/users`
- `GET /admin/users/{id}`
- `PATCH /admin/users/{id}`
- `GET /admin/sessions`
- `DELETE /admin/sessions/{id}`
- `GET /admin/audit-logs`
- `GET /admin/activity-logs`
- `GET /admin/ops`

## Platform

### `POST /devices`

Registers or updates the authenticated user's mobile device token for future push notifications.

```json
{
  "platform": "android",
  "device_token": "fcm-token",
  "device_name": "Pixel",
  "app_version": "1.0.0"
}
```

### `DELETE /devices/{id}`

Deletes one device token owned by the authenticated user.

### `POST /auth/google/token` and `POST /auth/microsoft/token`

These routes exist for mobile OAuth readiness and intentionally return `501` until a server-side `id_token` verifier is configured. Do not accept native provider tokens without signature/audience validation.

## Error Codes

- `401`: missing, invalid, or revoked bearer token.
- `403`: authenticated but not authorized by current role/policy.
- `404`: owned resource or linked member profile not found.
- `501`: endpoint contract exists but provider verifier/worker is not configured yet.
- `422`: validation error, wrong credentials, no actual profile changes, or missing unit context.
- `429`: rate limit exceeded.
