<p align="center">
   <img src="https://img.shields.io/github/stars/mrnikhilsingh/coffee-shop-management-system" alt="GitHub Repo stars">  
   <img src="https://img.shields.io/github/license/mrnikhilsingh/coffee-shop-management-system" alt="GitHub License">  
   <img src="https://img.shields.io/github/forks/mrnikhilsingh/coffee-shop-management-system" alt="GitHub forks">  
</p>

# Cafe Junction — Coffee Shop Management System

Web app for a café: menu, cart, checkout (Razorpay), QR table ordering, **3D live kitchen** (Three.js), admin panel, bookings, and optional PDF invoices by email.

**Stack:** PHP (≥ 8.0), MySQL, Apache, HTML/CSS/JS. Optional: Composer (DomPDF + PHPMailer for invoices).

![Cafe Junction Preview](https://github.com/mrnikhilsingh/coffee-shop-management-system/blob/main/images/website-screenshots/hero-section.png)

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
| `BOOKING_FEE_INR` | Table booking holding fee in INR (default `200`; use `0` only for local testing without charging) |

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

### After you `git clone` — full checklist

Follow these steps in order the first time you run the app on your machine.

#### 1. Clone the repository

```bash
git clone https://github.com/mrnikhilsingh/coffee-shop-management-system.git
```

Pick a folder name that matches how you will open the site in the browser, or plan to set `APP_URL` / `ADMIN_URL` in `.env` to match whatever path you use.

**XAMPP (Windows) — typical layout**

- Move or clone into your Apache document root, for example: `C:\xampp\htdocs\coffee`
- Then your public URL is `http://localhost/coffee` (folder name = last segment of the URL)

**macOS (MAMP) / Linux**

- Clone into your web root (e.g. `htdocs`, `www`, or a vhost directory) so Apache can serve the project folder.

#### 2. Start the web stack

- Start **Apache** and **MySQL** (XAMPP Control Panel, MAMP, or your distro’s services).
- Ensure **PHP 8.0+** is the version Apache uses (`php -v` in a terminal).

#### 3. Create `.env` from the example

From the **project root** (the folder that contains `config/`, `db/`, and `composer.json`):

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
copy .env.example .env
```

Edit `.env` and set at least:

- `APP_URL` and `ADMIN_URL` — must match the URL you type in the browser (no trailing slash).
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` — usually `localhost`, `root`, empty password, `ns_coffee` on default XAMPP.
- `RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET` — use [Razorpay](https://razorpay.com/) test keys for development.

Optional but useful:

- `BOOKING_FEE_INR` — table booking holding fee in INR; use `0` only for local testing without taking a payment.
- SMTP variables — if you want invoice emails; see the env table below.

> **Production:** the app requires a `.env` on the server. On **localhost**, if `.env` is missing, `config/config.php` falls back to defaults (still create `.env` for Razorpay and consistent URLs).

#### 4. Create the database and import the schema

1. Open **phpMyAdmin** (e.g. `http://localhost/phpmyadmin`) or use the MySQL client.
2. Create a database named **`ns_coffee`**, collation **utf8mb4** (utf8mb4_unicode_ci is fine).
3. Select that database → **Import** → choose **`db/ns_coffee.sql`** → run.

#### 5. Apply migrations (run in this order)

Run each file **once** against the `ns_coffee` database (phpMyAdmin **Import** or **SQL** tab), in this order:

| Order | File | Purpose |
|------:|------|---------|
| 1 | `db/migrations/2026-03-18_qr_ordering.sql` | QR table ordering |
| 2 | `db/migrations/2026-03-21_order_item_status.sql` | Per-line order item status |
| 3 | `db/migrations/2026-04-08_booking_razorpay.sql` | Bookings: Razorpay + email-related columns |
| 4 | `db/migrations/2026-04-08_inr_prices.sql` | Sample product prices in INR |

If MySQL reports that a column already exists, that migration may have been applied already; fix or skip as needed. The booking migration uses `ADD COLUMN IF NOT EXISTS` (MySQL 8.0.12+ / recent MariaDB).

#### 6. Install Composer dependencies (recommended)

From the project root:

```bash
composer install
```

This installs **DomPDF** and **PHPMailer** for PDF invoices and email. The storefront and admin may run without it, but invoice features expect these packages.

#### 7. Apache and `.htaccess`

If URLs look wrong or routes fail, enable **`mod_rewrite`** and ensure **`AllowOverride`** permits `.htaccess` in this directory (common XAMPP default for `htdocs`).

#### 8. Open the app in the browser

With the project at `http://localhost/coffee` and matching `.env`:

| Page | URL |
|------|-----|
| **Home / menu** | `http://localhost/coffee` |
| **Admin panel** | `http://localhost/coffee/admin-panel` |
| **QR table entry** | `http://localhost/coffee/qr.php?table=1` (change `1` to the table number) |
| **3D live kitchen** | `http://localhost/coffee/kitchen3d/` or `.../kitchen3d/?table=1` |

Register a user on the site for customer flows, or use admin credentials if your database / bootstrap flow provides them.

### Troubleshooting

- **“Connection failed” / DB error** — Check MySQL is running, `DB_*` in `.env`, and database exists.
- **“.env missing” on production** — Upload `.env` next to `config/` (project root). Localhost can fall back to defaults in `config/config.php` if `.env` is absent.
- **Wrong links / redirects** — `APP_URL` must match the folder you actually visit in the browser.
- **Razorpay** — Use test keys; ensure amount/currency match your Razorpay app settings.

---

## Demo

Live demo (if available): Website link.

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
