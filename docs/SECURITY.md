# Security Operations

## Credential Rotation

Rotate any credential that has ever appeared in a repository, deployment archive, chat, ticket, screenshot, or log. Removing a secret from the latest commit is not enough because Git history and third-party caches may still contain it.

Minimum rotation checklist:

- Change the database password at the hosting provider or database server.
- Update the production-only `config/database.local.php` file or deployment environment variables.
- Change FTP/SFTP, hosting panel, SMTP, OAuth, reCAPTCHA, and API credentials that were shared or committed.
- Revoke old OAuth client secrets and SMTP app passwords instead of reusing them.
- Confirm the application can connect with the new credentials.
- Remove secrets from Git history before making a repository public.

## Git History Purge

Use a history rewrite tool such as `git filter-repo` or BFG Repo-Cleaner on a fresh clone, then force-push only after every collaborator understands the rewrite.

Recommended sequence:

1. Rotate the leaked credentials first.
2. Create a fresh mirror clone.
3. Remove secret files and known secret strings from all commits.
4. Force-push rewritten branches and tags.
5. Invalidate old clones, deployment archives, and CI caches.
6. Ask GitHub support to clear cached views if sensitive data was public.

## OWASP ZAP Or Burp

Run dynamic scanning against a staging copy before production whenever possible.

Baseline ZAP pass:

```bash
docker run --rm -t owasp/zap2docker-stable zap-baseline.py -t https://example.com -r zap-report.html
```

Authenticated testing should use a dedicated test user and a separate administrator account. Do not scan production with destructive active checks unless you have backups and an approved maintenance window.

Recommended manual checks:

- CSRF protection on every state-changing route.
- Role boundaries between guest, user, moderator, and administrator.
- Upload MIME and extension bypass attempts.
- Password reset token reuse, expiry, and enumeration resistance.
- Admin settings, SMTP settings, and 2FA enable/disable flows.
- Rate limits and account lockouts for login and password reset.

## Backup And Restore

Create backups before every deployment, migration, and history rewrite.

Minimum backup set:

- MySQL/MariaDB dump.
- `public-assets/images` uploaded media.
- `config/database.local.php` or production environment variables.
- `storage/queue` if queued jobs must be preserved.

Restore drill:

1. Restore database into a clean staging database.
2. Restore uploaded media.
3. Apply local config or environment variables.
4. Run smoke checks for login, question pages, admin settings, uploads, email queue, sitemap, robots, and llms files.

## Incident Response

First hour:

- Put the site into maintenance mode or restrict admin access if active abuse is suspected.
- Rotate database, FTP/SFTP, SMTP, OAuth, and administrator credentials.
- Preserve web server logs, PHP logs, queue logs, and database audit logs.
- Disable compromised administrator accounts.
- Review `audit_logs` for settings, moderation, and deletion events.

After containment:

- Patch the root cause.
- Restore from a known-good backup if integrity is uncertain.
- Review uploaded files and queue payloads.
- Document timeline, affected data, and follow-up controls.
