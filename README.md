# هم‌اندیش (Hamandish) — Civic Engagement System

[![CI](https://github.com/ninakhairunnisa/hamandish/actions/workflows/ci.yml/badge.svg)](https://github.com/ninakhairunnisa/hamandish/actions/workflows/ci.yml)

A minimal, scalable, production-oriented **API-first** backend where citizens report civic
**Problems** and crowdsource **Solutions**, vote on them, comment, and have admins moderate.

Built with **Laravel + Sanctum**, following the **Service Layer** pattern, strict typing,
Form Requests, API Resources, and unified JSON exception handling.

## Features
- OTP authentication via **IPPanel** SMS gateway (cache-backed, rate-limited, brute-force protected)
- Problems feed with categories, images, search, and `latest`/`popular` sorting
- Featured problems section + admin moderation (approve / reject / feature / best-solution)
- Solutions with polymorphic up/down voting (cached net score) and self-vote protection
- Polymorphic comments on both Problems and Solutions
- Problem "supporters" counter (toggle)
- User profiles with avatar upload
- Database notifications (problem status changes, new solutions)
- **16 automated feature tests**, run in CI on PHP 8.3 & 8.4

## Documentation
Full endpoint reference: **[`docs/API.md`](docs/API.md)**

## Quick start
```bash
composer install
cp .env.example .env && php artisan key:generate
# configure IPPANEL_API_KEY / IPPANEL_PATTERN_CODE / IPPANEL_SENDER and DB_*
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan test
```
Seeded admin: phone `09000000000` (role `admin`).

## Architecture
```
app/
  Http/Controllers/Api/V1   # thin controllers
  Http/Requests             # validation
  Http/Resources            # uniform JSON output
  Http/Middleware/CheckAdmin
  Services                  # business logic (Auth, Problem, Vote, Support, SMS)
  Notifications
  Models                    # User, Problem, Solution, Vote, Comment, Category, Support
routes/api.php              # all v1 endpoints
docs/API.md                 # API reference
```
