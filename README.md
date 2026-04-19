# 420Forum Q&A Platform

Custom PHP 8.2+ Q&A platform with no Composer, no framework dependencies, root `index.php` entry, MVC flow, PDO prepared statements, file cache, file queue, RBAC, CSRF, rate limiting, and QAPage JSON-LD.

## Install

1. Point Apache document root at this directory.
2. Enable `mod_rewrite` and allow `.htaccess`.
3. Create a MySQL/MariaDB database and import `database/schema.sql`.
4. Set environment variables:

```bash
APP_NAME="420Forum Q&A"
APP_URL="https://your-domain.example"
APP_ENV=production
APP_SECURE_COOKIES=true
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qa_platform
DB_USERNAME=qa_user
DB_PASSWORD="replace-this"
```

5. Ensure PHP can write to `storage/logs`, `storage/cache`, `storage/sessions`, and `storage/queue`.
6. Run queued jobs from cron:

```bash
php queue-worker.php
```

For local development over plain HTTP, set `APP_SECURE_COOKIES=false`.

The first account registered on a fresh database is automatically assigned the `administrator` role.

## Architecture

Request flow:

`/index.php -> Core\Kernel -> Core\Router -> Middleware -> Controller -> Service -> Model -> View`

Important directories:

- `core`: framework kernel, router, request/response, view, PDO wrapper, cache, sessions, CSRF, rate limiting, queue.
- `app/Controllers`: request endpoints.
- `app/Services`: auth, Q&A, moderation, admin, SEO, safe uploads.
- `app/Models`: PDO-backed data access.
- `views`: escaped PHP templates.
- `public-assets`: CSS and JavaScript.
- `storage`: logs, sessions, cache, queue jobs.

Feature areas:

- Admins manage users, SEO settings, categories, and subcategories from `/admin`.
- Moderators resolve/reject flags, edit/delete content, and get an audit log from `/moderator`.
- Users can update profiles, receive notifications, verify email, and reset passwords.
- Queued jobs handle notification records and PHP `mail()` messages when hosting mail is configured.

## Security Notes

- All database access goes through PDO prepared statements.
- Passwords use `password_hash()` with `PASSWORD_ARGON2ID`.
- State-changing routes require CSRF tokens.
- Sessions use strict mode, cookie-only sessions, HttpOnly cookies, SameSite=Lax, periodic ID regeneration, and optional secure cookies.
- Output is escaped with `h()`; Markdown is converted only after escaping raw input.
- Apache blocks direct access to `app`, `config`, `core`, `database`, `storage`, and `views`.
- Rate limits protect login, posting, voting, commenting, and flagging.
- Uploads validate MIME type with `finfo`, cap size, and use random filenames.

## SEO

Question pages use clean URLs such as `/question/how-to-install-nginx-on-ubuntu`, canonical tags, meta descriptions, OpenGraph/Twitter metadata, internal related-question links, breadcrumbs, and QAPage JSON-LD with nested Question and Answer entities.

Dynamic endpoints:

- `/sitemap.xml`
- `/robots.txt`
- `/llms.txt`

Static fallback files `robots.txt` and `llms.txt` are included for hosts that serve existing files before PHP routing. Replace `https://example.com` with your production URL if you keep them static.
