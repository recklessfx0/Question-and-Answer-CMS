# Question And Answer Platform

I built this platform for my own private projects, mainly because every alternative I found was outdated, insecure, and far behind current coding and security standards. So I decided to start fresh.

The CMS runs on PHP 8.5+ with no Composer dependencies. The root `index.php` entry point uses an MVC flow, PDO prepared statements, a file cache, a file queue, role-based access control, CSRF protection, and rate limiting.

One thing I paid special attention to is how AI is reshaping SEO. LLMs are now crawling and interpreting content differently than traditional search engines, so I optimized this platform to be highly machine-readable, with proper QAPage JSON-LD schemas and a range of other SEO tweaks baked in from the start.

The platform is fully brandable through environment variables and database settings, making it easy to reuse across different communities and projects. Whether you're building a niche knowledge base, a support forum, or a community question and answer site, this is a solid foundation to build on.

## Install

1. Install PHP 8.5+ with Argon2id and the PDO MySQL, Mbstring, Fileinfo, OpenSSL, SimpleXML, and Iconv extensions. Enable OPcache in the web PHP runtime.
2. Create an empty MySQL or MariaDB database and grant the installer account permission to create and alter tables in it.
3. Set these environment variables for the installer and the web runtime. Supply the database password through your deployment environment.

```bash
APP_NAME="Question And Answer Platform"
APP_URL="https://your-domain.example"
APP_ENV=production
APP_SECURE_COOKIES=true
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qa_platform
DB_USERNAME=qa_user
DB_PASSWORD="replace-this"
```

4. Set `CMS_INSTALL_PASSWORD` for the installer process using your secret manager or service environment. Run the command from the project root. The installer creates the schema and the first administrator. Use a lowercase administrator username with hyphens between words and a password of 12 to 128 characters.

```bash
php install.php site-admin admin@your-domain.example
```

5. Remove `CMS_INSTALL_PASSWORD` from the environment after setup. Point Apache's document root at the project directory, enable `mod_rewrite`, and allow the supplied `.htaccess`. Verify that the PHP service account can write to `storage/logs`, `storage/cache`, `storage/sessions`, and `storage/queue`. If the installer ran as a different user, set the storage ownership or ACLs accordingly. If you use a different web server, configure the same access restrictions and front-controller routing before exposing the site.
6. Run queued jobs from cron with the application database environment:

```bash
php queue-worker.php
```

For local development over plain HTTP, set `APP_SECURE_COOKIES=false`.

Public registration creates regular users. Existing administrators keep their roles. On an existing site, back up the database and apply any outstanding migrations before upgrading. If a fresh schema import fails partway through, inspect the error and restore a clean database before retrying. The installer does not overwrite existing tables.

## Architecture

Request flow:

`/index.php -> Core\Kernel -> Core\Router -> Middleware -> Controller -> Service -> Model -> View`

Important directories:

- `core`: framework kernel, router, request/response, view, PDO wrapper, cache, sessions, CSRF, rate limiting, queue.
- `app/Controllers`: request endpoints.
- `app/Services`: auth, questions and answers, moderation, admin, SEO, safe uploads.
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
- Administrator accounts can enable TOTP two-factor authentication from profile settings.
- Admin settings changes are written to the audit log with secret values redacted.
- SMTP email can be configured from the admin settings page.
- Languages and interface translations can be managed from the admin dashboard. English is the seeded default, and visitors are served an active translation automatically when their browser language matches one.
- Operational security runbooks live in `docs/SECURITY.md`.

## SEO

Question pages use clean URLs such as `/question/how-to-install-nginx-on-ubuntu`, canonical tags, meta descriptions, OpenGraph/Twitter metadata, internal related-question links, breadcrumbs, and QAPage JSON-LD with nested Question and Answer entities.

Dynamic endpoints:

- `/sitemap.xml`
- `/robots.txt`
- `/llms.txt`
- `/llms-full.txt`
- `/question/{slug}/text` serves the public discussion as Markdown. The HTML page advertises it as an alternate, and the Markdown response links back to the canonical HTML page.

Static fallback files `robots.txt` and `llms.txt` are included for hosts that serve existing files before PHP routing. Replace `https://example.com` with your production URL if you keep them static.
