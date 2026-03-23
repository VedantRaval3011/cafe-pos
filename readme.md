<p align="center">
   <img src="https://img.shields.io/github/stars/mrnikhilsingh/coffee-shop-management-system" alt="GitHub Repo stars">  
   <img src="https://img.shields.io/github/license/mrnikhilsingh/coffee-shop-management-system" alt="GitHub License">  
   <img src="https://img.shields.io/github/forks/mrnikhilsingh/coffee-shop-management-system" alt="GitHub forks">  
</p>

# NS Coffee — Coffee Shop Management System

Web app for a café: menu, cart, checkout (Razorpay), QR table ordering, **3D live kitchen** (Three.js), admin panel, bookings, and optional PDF invoices by email.

**Stack:** PHP (≥ 8.0), MySQL, Apache, HTML/CSS/JS. Optional: Composer (DomPDF + PHPMailer for invoices).

![NS Coffee Preview](https://github.com/mrnikhilsingh/coffee-shop-management-system/blob/main/images/website-screenshots/hero-section.png)

---

## Prerequisites

| Requirement | Notes |
|-------------|--------|
| **PHP** | 8.0+ (8.1+ recommended; matches `composer.json`) |
| **MySQL / MariaDB** | For products, orders, users, sessions |
| **Apache** (or similar) | With `mod_rewrite` if you use `.htaccess`; **XAMPP** is fine on Windows |
| **Composer** | Optional — only needed for invoice PDF + email sending |

---

## Environment variables (`.env`)

Configuration is loaded from **`.env`** in the **project root** (same folder as `config.php`’s parent). **Do not commit** `.env` to Git; use a secret manager on production.

1. Copy the example file:

   ```bash
   cp .env.example .env
   ```

   On Windows (PowerShell), from the project folder:

   ```powershell
   copy .env.example .env
   ```

2. Edit `.env` and set the values below.

### Required — App & database

| Variable | Description | Example (local XAMPP) |
|----------|-------------|------------------------|
| `APP_URL` | Public site base URL (no trailing slash) | `http://localhost/coffee` |
| `ADMIN_URL` | Admin panel base URL | `http://localhost/coffee/admin-panel` |
| `DB_HOST` | MySQL host | `localhost` |
| `DB_USER` | MySQL user | `root` |
| `DB_PASS` | MySQL password | *(empty on default XAMPP)* |
| `DB_NAME` | Database name | `ns_coffee` |

> If the project lives in a **different folder name**, change `APP_URL` / `ADMIN_URL` accordingly (e.g. `http://localhost/coffee-shop-management-system`).

### Required for payments — Razorpay

| Variable | Description |
|----------|-------------|
| `RAZORPAY_KEY_ID` | Key ID from Razorpay Dashboard (test or live) |
| `RAZORPAY_KEY_SECRET` | Secret key — never expose in frontend |

Use test keys while developing. Webhooks/callback URLs must match your `APP_URL` in production.

### Optional — SMTP (invoice emails)

| Variable | Description |
|----------|-------------|
| `SMTP_HOST` | e.g. `smtp.gmail.com` |
| `SMTP_PORT` | e.g. `587` |
| `SMTP_USERNAME` | SMTP login |
| `SMTP_PASSWORD` | App password / token |
| `SMTP_FROM_EMAIL` | From address |
| `SMTP_FROM_NAME` | Display name |

If omitted, invoices may still generate locally (see `payments/invoice_service.php`) but email may be skipped.

### Optional — admin bootstrap (first login)

| Variable | Description |
|----------|-------------|
| `ADMIN_LOGIN_NAME` | Display name |
| `ADMIN_LOGIN_EMAIL` | Email |
| `ADMIN_LOGIN_PASSWORD` | Password |

Used only if your project uses the bootstrap admin flow.

---

## How to start (local)

### 1. Clone or copy the project

Place the project under your web root, e.g.:

`C:\xampp\htdocs\coffee`

### 2. Create `.env`

Copy `.env.example` → `.env` and fill **App URLs**, **database**, and **Razorpay** keys (see above).

### 3. Create the database

1. Start **Apache** and **MySQL** in XAMPP (or your stack).
2. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
3. Create a database named **`ns_coffee`** (utf8mb4).
4. Import **`db/ns_coffee.sql`** (Import tab → choose file → Go).

### 4. **Apply migrations** (QR ordering + per-line order status)

Run these SQL files **in order** on the `ns_coffee` database (SQL tab or Import):

1. `db/migrations/2026-03-18_qr_ordering.sql`
2. `db/migrations/2026-03-21_order_item_status.sql`

If a migration says a column already exists, skip that file or adjust manually.

### 5. Install PHP dependencies (optional)

From the project root:

```bash
composer install
```

Needed for PDF invoices and email. If you skip this, core ordering may still work without emailed invoices.

### 6. Open the site

With default XAMPP paths and `APP_URL=http://localhost/coffee`:

| Page | URL |
|------|-----|
| **Home / menu** | `http://localhost/coffee` |
| **Admin panel** | `http://localhost/coffee/admin-panel` |
| **QR table entry** | `http://localhost/coffee/qr.php?table=1` (replace `1` with table number) |
| **3D live kitchen** | `http://localhost/coffee/kitchen3d/` or `.../kitchen3d/?table=1` (table-scoped) |

### 7. Troubleshooting

- **“Connection failed” / DB error** — Check MySQL is running, `DB_*` in `.env`, and database exists.
- **“.env missing” on production** — Upload `.env` next to `config/` (project root). Localhost can fall back to defaults in `config/config.php` if `.env` is absent.
- **Wrong links / redirects** — `APP_URL` must match the folder you actually visit in the browser.
- **Razorpay** — Use test keys; ensure amount/currency match your Razorpay app settings.

---

## Demo

Live demo (if available): [Website Link](https://nscoffee.free.nf/).

---

## Features (summary)

- User registration/login, menu, cart, checkout
- **Razorpay** payments
- **QR ordering** (`qr.php?table=N`) and **3D kitchen** (`kitchen3d/`)
- **Admin** products, orders (including per-line **item status**), bookings
- Optional **PDF invoices** + email (Composer packages)

---

## Screenshots

| Login Page |
| ---------- |
| ![Login Page](https://github.com/mrnikhilsingh/coffee-shop-management-system/blob/main/images/website-screenshots/login_page.png) |

| Register Page |
| ------------- |
| ![Register Page](https://github.com/mrnikhilsingh/coffee-shop-management-system/blob/main/images/website-screenshots/register_page.png) |

| Menu Page |
| --------- |
| ![Menu Page](https://github.com/mrnikhilsingh/coffee-shop-management-system/blob/main/images/website-screenshots/menu_page.png) |

---

## Technologies

- HTML, CSS, JavaScript
- PHP 8+, MySQL
- Three.js (3D kitchen), Razorpay Checkout

---

## Contributing

Pull requests are welcome. For major changes, open an issue first.

1. Fork the repository.
2. Create a branch for your feature/fix.
3. Commit with clear messages.
4. Open a pull request.

---

## Project admin

[Nikhil Singh](https://github.com/mrnikhilsingh) — [m.j882600@gmail.com](mailto:m.j882600@gmail.com)

---

## License

This project is licensed under the [MIT LICENSE](./LICENSE).
