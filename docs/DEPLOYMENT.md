# راهنمای استقرار روی هاست اشتراکی

## ساختار فایل‌ها روی سرور

آپلود کامل پروژه (بدون `node_modules` و `vendor`) در مسیر دلخواه:

```
/home/username/hamandish/       ← root پروژه (خارج از public_html)
    .htaccess                   ← redirect به public/
    app/
    bootstrap/
    config/
    database/
    public/                     ← اینجا باید در public_html لینک/آپلود بشه
        .htaccess
        index.php
        build/                  ← خروجی npm run build
    resources/
    routes/
    storage/
    ...
```

### گزینه الف — کل پروژه داخل `public_html/hamandish/`

اگر کل پروژه را در `public_html/hamandish/` آپلود کنید:

- فایل `.htaccess` در ریشه پروژه (`public_html/hamandish/.htaccess`) تمام درخواست‌ها را به `public/` هدایت می‌کند.
- سایت روی `https://menon.ir/hamandish/` در دسترس خواهد بود.

### گزینه ب — Document Root روی `public/`

اگر cPanel یا هاست شما امکان تنظیم Document Root را می‌دهد، آن را روی:

```
/home/username/hamandish/public
```

قرار دهید. در این حالت به `.htaccess` ریشه نیاز ندارید.

---

## مراحل نصب

### ۱. تنظیم `.env`

```bash
cp .env.example .env
```

مقادیر زیر را ویرایش کنید:

```dotenv
APP_NAME=هم‌اندیش
APP_ENV=production
APP_DEBUG=false
APP_KEY=                        # با php artisan key:generate پر می‌شود
APP_URL=https://menon.ir/hamandish

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=menon_hamandish
DB_USERNAME=your_user
DB_PASSWORD=your_pass

VITE_APP_BASE=/hamandish/
VITE_BUILD_BASE=/hamandish/build/

APP_LOCALE=fa

IPPANEL_API_KEY=...
BALE_BOT_TOKEN=...
EITAA_BOT_TOKEN=...
```

### ۲. نصب وابستگی‌های PHP

```bash
composer install --no-dev --optimize-autoloader
```

### ۳. تولید کلید

```bash
php artisan key:generate
```

### ۴. اجرای Migration

```bash
php artisan migrate --force
```

### ۵. ساخت فرانت‌اند

روی **سرور محلی یا CI** (نه سرور اشتراکی):

```bash
cp .env.example .env          # یا همان .env پروداکشن را بگذارید
npm ci
npm run build
```

سپس پوشه `public/build` را روی سرور آپلود کنید.

### ۶. لینک Storage

```bash
php artisan storage:link
```

### ۷. بهینه‌سازی Cache

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## تنظیم Mini-App در بله / ایتا

| فیلد | مقدار |
|---|---|
| آدرس Mini-App | `https://menon.ir/hamandish/` |
| Webhook بله | `https://menon.ir/hamandish/api/v1/integrations/bale/webhook?secret=BALE_WEBHOOK_SECRET` |
| Webhook ایتا | `https://menon.ir/hamandish/api/v1/integrations/eitaa/webhook?secret=EITAA_WEBHOOK_SECRET` |

---

## رفع مشکل Permission

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```
