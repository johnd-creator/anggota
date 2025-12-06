# Integrasi API Laporan

## Autentikasi
- Gunakan header `X-API-Token` dengan nilai yang sama seperti `APP_API_TOKEN` pada konfigurasi aplikasi.
- Respons `401 Unauthorized` jika token tidak valid.

## Endpoint
- GET `/api/reports/growth`
  - Query: `unit_id`, `date_start`, `date_end`
  - Respons: `{ series: [{ label: 'YYYY-MM', value: number }, ...] }`
- GET `/api/reports/mutations`
  - Query: `unit_id`, `status`, `date_start`, `date_end`
  - Respons: `{ dist: [{ to_unit_id: number, c: number }, ...] }`
- GET `/api/reports/documents`
  - Query: `unit_id`, `status`
  - Respons: `{ items: [{ id, full_name, email, organization_unit_id, photo_path, documents }, ...] }`

## Export CSV
- POST `/reports/{type}/export` dengan body filter (JSON atau form):
  - `type` = `growth` | `mutations` | `documents`
  - Header respons: `Content-Type: text/csv`
  - File dinamai dengan timestamp.

