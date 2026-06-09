# هم‌اندیش — API Reference (v1)

Base URL: `/api/v1`
Auth: Laravel Sanctum (Bearer token). Send `Authorization: Bearer <token>` on protected routes.
All responses are JSON. Collections are paginated (15/page) and wrapped in `data` + `meta`/`links`.
Single-resource responses return the object directly (not wrapped).

---

## Authentication

### `POST /auth/send-otp` — Guest · `throttle:10,1`
Generate and SMS a 5-digit OTP (valid 120s). Max 1 request / 2 min per phone+IP.

| Field | Rules |
|---|---|
| `phone` | required, Iranian mobile `^09\d{9}$` |

**200** `{ "message": "کد OTP ارسال شد." }`
**422** validation · **429** rate-limited · **503** SMS gateway failure (slot not consumed — safe to retry)

### `POST /auth/verify-otp` — Guest · `throttle:10,1`
Verify the OTP, auto-register first-time users, issue a token. After 5 wrong attempts the OTP is invalidated.

| Field | Rules |
|---|---|
| `phone` | required, `^09\d{9}$` |
| `token` | required, 5 digits |

**200**
```json
{ "token": "1|xxxxx", "user": { "id": 1, "phone": "0912...", "role": "user" } }
```
**422** invalid/expired OTP

### `GET /auth/me` — Auth
Returns the authenticated user.

---

## Messenger login (Bale / Eitaa mini-app)

Users open the web app inside Bale or Eitaa. Accounts are **keyed by phone**, so the
same person logging in from either messenger maps to one user (no duplicates).

### `POST /auth/messenger` — Guest · `throttle:30,1`
Authenticate a mini-app session from its signed `init_data`.

| Field | Rules |
|---|---|
| `provider` | required, `bale`\|`eitaa` |
| `init_data` | required, the host SDK's signed init-data string |

- **200** `{ "token": "...", "user": {...} }` — identity already linked
- **409** `{ "need_contact": true, "bot_deep_link": "https://ble.ir/<bot>" }` — verified user must share their phone with the bot first
- **401** invalid init-data signature

### `POST /integrations/{provider}/webhook?secret=...` — Bot platform only
Bot webhook. On a shared-contact update it normalises the phone, finds-or-creates the
phone-keyed user, and links the messenger identity. Secured by `services.{provider}.webhook_secret`.
Always returns **200** (so the bot doesn't retry); **401** on a bad secret.

**Phone-sync guarantee:** sharing the same number in Bale and Eitaa produces one `users`
row with two `messenger_identities`.

---

## Categories

### `GET /categories` — Public
List of problem categories (`id, title, slug, icon, color`).

---

## Problems

### `GET /problems` — Public · Paginated
Approved problems only. Query params:

| Param | Description |
|---|---|
| `search` | matches title/description |
| `category_id` | filter by category |
| `sort` | `latest` (default) or `popular` (by supporters) |

### `GET /problems/featured` — Public · Paginated
Approved + `is_featured = true` (the «مشکلات برگزیده» section).

### `GET /problems/popular` — Public · Paginated
Approved, ordered by `supports_count` desc (the «مشکلات پیشنهادی کاربران» section).

### `GET /problems/{problem}` — Public
Single problem. Non-approved problems are visible only to their owner or an admin (otherwise 404).

### `POST /problems` — Auth · `multipart/form-data`
Create a problem (starts as `pending`).

| Field | Rules |
|---|---|
| `title` | required, 5–255 |
| `description` | required, ≥20 |
| `category_id` | nullable, exists |
| `image` | nullable, image ≤4MB |

**201** problem resource

### `POST /problems/{problem}/support` — Auth
Toggle the current user's support. **200** `{ "supported": true, "supports_count": 12 }`

**Problem resource shape**
```json
{
  "id": 1, "title": "...", "description": "...", "image_url": null,
  "status": "approved", "is_featured": true, "supports_count": 156,
  "solutions_count": 12, "comments_count": 23,
  "category": { "id": 1, "title": "ترافیک", "icon": "car", "color": "#F97316" },
  "user": { "id": 1, "first_name": "...", "avatar_url": null },
  "best_solution": { ... }, "is_supported": true,
  "created_at": "...", "updated_at": "..."
}
```

---

## Solutions

### `GET /problems/{problem}/solutions` — Public · Paginated
Ordered by `votes_count` desc (net score).

### `POST /problems/{problem}/solutions` — Auth
Add a solution (problem must be `approved`). Notifies the problem owner.

| Field | Rules |
|---|---|
| `content` | required, ≥10 |

### `POST /solutions/{solution}/vote` — Auth
Up/down-vote. One vote per user (upsert). **Cannot vote on your own solution (422).**

| Field | Rules |
|---|---|
| `type` | required, `1` or `-1` |

**200** `{ "vote": {...}, "votes_count": 7 }`

### `DELETE /solutions/{solution}/vote` — Auth
Remove your vote. **200** `{ "votes_count": 6 }`

---

## Comments (polymorphic)

### `GET /problems/{problem}/comments` — Public · Paginated
### `POST /problems/{problem}/comments` — Auth
### `GET /solutions/{solution}/comments` — Public · Paginated
### `POST /solutions/{solution}/comments` — Auth

| Field | Rules |
|---|---|
| `content` | required, ≥3 |

Comment resource includes `commentable_type` (`Problem`|`Solution`) and `commentable_id`.

---

## Profile

### `GET /profile` — Auth
### `PUT|POST /profile` — Auth · `multipart/form-data`
| Field | Rules |
|---|---|
| `first_name` | nullable, ≤100 |
| `last_name` | nullable, ≤100 |
| `avatar` | nullable, image ≤2MB |

### `GET /profile/problems` — Auth · Paginated
The current user's own problems (any status).

---

## Notifications

### `GET /notifications` — Auth · Paginated
### `GET /notifications/unread-count` — Auth → `{ "unread_count": 3 }`
### `PATCH /notifications/{id}/read` — Auth
### `PATCH /notifications/read-all` — Auth → `204`

Fired automatically on: problem status change (to owner), new solution (to problem owner).

---

## Admin (`admin` middleware — role = `admin`)

### `GET /admin/problems/pending` — Paginated
Problems awaiting moderation.

### `PATCH /admin/problems/{problem}/status`
| Field | Rules |
|---|---|
| `status` | required, `approved`\|`rejected` |

### `PATCH /admin/problems/{problem}/featured`
| Field | Rules |
|---|---|
| `is_featured` | required, boolean |

### `PATCH /admin/problems/{problem}/best-solution`
| Field | Rules |
|---|---|
| `solution_id` | required, exists, must belong to the problem |

Non-admins receive **403**.

---

## Error format
```json
{ "message": "خطا در اعتبارسنجی داده‌ها.", "errors": { "phone": ["..."] } }
```
| Code | Meaning |
|---|---|
| 401 | unauthenticated |
| 403 | forbidden (admin only) |
| 404 | not found / hidden |
| 422 | validation / business rule |
| 429 | throttled |
| 503 | SMS gateway unavailable |

---

## Setup
```bash
composer install
cp .env.example .env && php artisan key:generate
# set IPPANEL_API_KEY / IPPANEL_PATTERN_CODE / IPPANEL_SENDER and DB_*
php artisan migrate --seed
php artisan storage:link   # serve uploaded images/avatars
php artisan test           # 16 tests
```
Default seeded admin: phone `09000000000` (role `admin`).
