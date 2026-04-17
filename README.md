# 🚀 Question and Answer Platform
**High-performance, secure, SEO-optimized PHP Q&A system (No framework, No Composer)**

Q&A Platform is a **lightweight, production-ready question & answer system** built with **pure PHP 8.2+**, designed for **maximum performance, strong security, and SEO dominance**.

<video src="video-questionandanswer.mp4" controls width="600"></video>
![video](video-questionandanswer.mp4)

This project follows a **custom MVC architecture** with zero external dependencies, giving developers full control and transparency.

---

## 🔥 Key Features

- ⚡ Ultra-fast core (no Composer, no framework overhead)
- 🧠 Custom MVC architecture
- 🔐 Enterprise-grade security (Argon2ID, CSRF, RBAC)
- 📈 Advanced SEO optimization (QAPage JSON-LD, clean URLs)
- 🗂️ Role-based access control (Admin / Moderator / User)
- 📨 File-based queue system (no Redis required)
- 💾 File caching for performance
- 🚫 Built-in rate limiting & abuse protection
- 📬 Notification system + email support
- 🧩 Extensible service-based architecture

---

## 🧱 Tech Stack

- PHP 8.2+
- MySQL / MariaDB
- PDO (prepared statements only)
- Apache (.htaccess + mod_rewrite)

---

## ⚙️ Installation

### 1. Configure Web Server
Point your Apache document root to this directory.

Enable:
- mod_rewrite
- .htaccess support

---

### 2. Database Setup

Import the schema:

```bash
database/schema.sql
```

---

### 3. Environment Configuration

```bash
APP_NAME="Q&A Platform"
APP_URL="https://your-domain.example"
APP_ENV=production
APP_SECURE_COOKIES=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qa_platform
DB_USERNAME=qa_user
DB_PASSWORD="replace-this"
```

---

### 4. File Permissions

Ensure write access:

```
storage/logs
storage/cache
storage/sessions
storage/queue
```

---

### 5. Queue Worker (CRON)

```bash
php queue-worker.php
```

Example cron:

```bash
* * * * * php /path-to-project/queue-worker.php
```

---

### 6. Local Development

```bash
APP_SECURE_COOKIES=false
```

---

### 🔑 First User

The first registered account becomes:

```
administrator
```

---

## 🏗️ Architecture

### Request Flow

```
/index.php
  → Core\Kernel
  → Core\Router
  → Middleware
  → Controller
  → Service
  → Model
  → View
```

---

### 📁 Structure

```
core/              → Framework core
app/Controllers    → Endpoints
app/Services       → Business logic
app/Models         → Database layer
views/             → Templates
public-assets/     → CSS / JS
storage/           → Logs, cache, sessions, queue
```

---

## 👥 Features by Role

### Admin (`/admin`)
- User management
- SEO configuration
- Categories & structure

### Moderator (`/moderator`)
- Content moderation
- Flag handling
- Audit logs

### User
- Profiles
- Notifications
- Email verification
- Password reset

---

## 🔐 Security

- PDO prepared statements only
- Password hashing with Argon2ID
- CSRF protection
- Secure sessions (HttpOnly, SameSite, regeneration)
- Rate limiting (login, posting, voting, etc.)
- Secure uploads (MIME validation, size limits, random names)
- Protected directories via Apache rules

---

## 📈 SEO Optimization

- Clean URLs  
  `/question/how-to-install-nginx-on-ubuntu`

- Canonical tags
- Meta descriptions
- OpenGraph & Twitter cards
- Breadcrumbs
- Internal linking
- QAPage JSON-LD structured data

---

### 🔎 SEO Endpoints

```
/sitemap.xml
/robots.txt
/llms.txt
```

Static fallback files included:

```
robots.txt
llms.txt
```

---

## ⚡ Performance

- File-based caching
- Minimal memory usage
- Optimized PDO queries
- No dependency overhead
- Lightweight queue system

---

## 🧩 Extensibility

- Modular service architecture
- Easy controller extension
- SEO customization ready
- Scalable structure for APIs or integrations

---

## 📌 Use Cases

- Q&A communities
- Knowledge bases
- Technical forums
- Niche communities
- SEO content platforms

---

## 📜 License (GPL v3)

This project is licensed under the **GNU General Public License v3.0**.

### ⚠️ Important Terms

- ✅ You are free to:
  - Use this CMS for personal and commercial projects
  - Modify, extend, and improve the code
  - Distribute your own versions

- ❗ You MUST:
  - Release any modified or extended version under the **same GPL v3 license**
  - Share your changes publicly when distributing the software
  - Keep this project open source

### 💡 Philosophy

**Sharing is caring.**

If you build something better, contribute it back to the community.  
This ensures the CMS stays open, transparent, and continuously improving.

---

## © Copyright

© 2026 Q&A Platform

This software is free and open-source under the GPL v3 license.  
Any redistribution, modification, or derivative work must remain open-source and licensed under GPL v3.

Unauthorized attempts to relicense this software as proprietary are strictly prohibited.

---

## 🤝 Contributing

Contributions are welcome:

- Bug reports
- Feature suggestions
- Pull requests

---

## 🚀 Roadmap

- REST API
- WebSocket notifications
- Advanced search
- Plugin system
- Multi-language support

---

## ⭐ Final Note

This CMS is built for developers who want:

- Full control
- Maximum performance
- Strong SEO
- No framework limitations

If you use it, improve it, and grow it — **give something back**.
