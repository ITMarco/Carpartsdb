# Car Parts DB — Used Car Parts Marketplace

A PHP-based community marketplace for buying and selling used car parts. Sellers list their spare parts with photos, specs and prices; buyers browse by make, model, year or OEM number.

## Features

- **Browse & search** — filter parts by make, model, year, condition or keyword/OEM number; paginated results with thumbnails
- **Part listings** — title, price, condition (0–5), OEM/replacement part numbers, description, stock count
- **Photo upload** — drag-and-drop (desktop) or tap-to-choose (mobile) photo upload directly after listing a part; multiple photos per part with lightbox viewer
- **Also fits** — parts can list additional compatible makes/models (e.g. a Supra motor mount that also fits a Soarer)
- **Year auto-fill** — year range is automatically derived from the selected car model; can be overridden manually
- **User accounts** — sellers register with email + password (bcrypt); session-based auth
- **My parts** — sellers manage their own listings
- **Admin panel** — dashboard with links to all admin tools
- **Makes & models** — seeded with 30 makes / 450 models; managed via admin page
- **Theme system** — CSS custom property theming; admin creates/activates themes; users pick via a floating 🎨 button (cookie-based, 1-year)
- **Homepage news** — admin-managed news items (`HOME_NEWS` table)
- **Comments moderation** — visitors comment; admin moderates with new-badge in footer
- **Daily stats** — sessions, searches, parts added, photos added (`STATS_DAILY` table)
- **IP whitelist** — trusted IPs bypass rate limiting and are excluded from stats
- **Security** — CSRF tokens, prepared statements, bcrypt passwords, session fixation prevention, login rate limiting (5 attempts / 15 min lockout), LFI-resistant whitelist router

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.0 |
| Database | MySQL / MariaDB via MySQLi (OOP style) |
| Frontend | HTML, CSS custom properties, vanilla JavaScript |
| Server | Synology NAS (Apache/nginx) |

## Project Structure

```
index.php                   # Entry point and whitelist router (?navigate=PAGE)
config.php                  # DB constants (DB_HOST, DB_USER, DB_PASS, DB_NAME)
connection.php              # MySQLi bootstrap — gitignored, contains credentials
session_manager.php         # Session handling, CSRF, rate limiting, auth helpers
login_helper.php            # Login rate limiting (LOGIN_ATTEMPTS table)
ip_whitelist_helper.php     # Trusted IPs (IP_WHITELIST table)
settings_helper.php         # Key-value settings store (SETTINGS table)
stats_helper.php            # Daily stats helpers (STATS_DAILY table)
theme_helper.php            # Theme system: THEMES table, CSS injection, user picker
image_helper.php            # Image processing (resize/convert on upload)
makes_helper.php            # CAR_MAKES + CAR_MODELS tables, 450 model seed data
users_helper.php            # USERS table, auth helpers
parts_helper.php            # PARTS + PART_COMPAT tables, photo helpers
car_stats_helper.php        # Admin: per-part view stats
comment_helper.php          # CAR_COMMENTS table helpers
export_database.php         # Standalone DB export utility (admin direct-access)

engine/
  header.engine.php         # HTML <head>, theme CSS injection
  body_top.engine.php       # Page wrapper, hamburger nav, online users + logged-in user
  body_bottom.engine.php    # Footer, admin/logout links, floating theme picker
  style.css                 # CSS variable-driven stylesheet

pages/                      # Page includes (routed via $allowed_pages whitelist)
  home.php                  # Homepage: stats, recently listed (with thumbnails), news
  browse.php                # Browse/search parts with thumbnails and pagination
  viewpart.php              # Part detail: photos, specs, "also fits" list
  addpart.php               # Add a listing (step 1: form; step 2: drag-drop photo upload)
  processaddpart.php        # POST handler: saves part, compat entries, redirects to step 2
  editpart.php              # Edit listing: year auto-fill, compat rows
  processeditpart.php       # POST handler: updates part + compat entries
  deletepart.php            # Delete a listing (owner or admin)
  uploadpartimage.php       # Upload photo — supports both standard POST and AJAX (fetch)
  deletepartimage.php       # Remove a photo from a listing
  myparts.php               # Seller's own listings
  secureadmin.php           # Login form
  logout.php                # Session logout
  adminpanel.php            # Admin dashboard
  adminmakes.php            # Admin: manage car makes and models
  insertuser.php            # Admin: add user form
  processinsertuser.php     # Admin: save new user
  edituser.php              # Admin: edit user form
  processedituser.php       # Admin: save user edits
  themeadmin.php            # Admin: create/edit/activate/publish themes
  homenews.php              # Admin: manage homepage news items
  commentadmin.php          # Admin: moderate comments
  ipwhitelist.php           # Admin: manage trusted IP addresses
  carstats.php              # Admin: part view statistics
  about.php                 # About page
  address.php               # Contact / address page
  privacyverklaring.php     # Privacy policy (Dutch)

data/
  menu.data.php             # Navigation menu items
  title.data.php            # Browser tab title
  error404.data.php         # 404 error page

parts/{id}/                 # Per-part photos (gitignored)
images/                     # Site-wide images and header backgrounds
```

## Database Tables

### Auto-created by PHP helpers on first use

| Table | Helper | Purpose |
|---|---|---|
| `USERS` | `users_helper.php` | User accounts (email, bcrypt password, isadmin, is_member) |
| `CAR_MAKES` | `makes_helper.php` | 30 makes, seeded once |
| `CAR_MODELS` | `makes_helper.php` | 450 models with year ranges |
| `PARTS` | `parts_helper.php` | Part listings |
| `PART_COMPAT` | `parts_helper.php` | Extra make/model fitments per part |
| `THEMES` | `theme_helper.php` | CSS variable theme templates |
| `SETTINGS` | `settings_helper.php` | Key-value app settings |
| `STATS_DAILY` | `stats_helper.php` | Daily counters |
| `HOME_NEWS` | `home.php` | Admin-managed news items |
| `CAR_COMMENTS` | `comment_helper.php` | Visitor comments |
| `LOGIN_ATTEMPTS` | `login_helper.php` | Login rate limiting |
| `IP_WHITELIST` | `ip_whitelist_helper.php` | Trusted IPs |

### PARTS columns

| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `seller_id` | INT | FK → USERS.id |
| `make_id` | INT | FK → CAR_MAKES.id |
| `model_id` | INT NULL | FK → CAR_MODELS.id |
| `title` | VARCHAR(255) | Part name |
| `description` | TEXT NULL | Free-text details |
| `year_from` | SMALLINT | Auto-filled from model if not supplied |
| `year_to` | SMALLINT NULL | NULL = ongoing production |
| `price` | DECIMAL(10,2) | |
| `condition` | TINYINT | 0 (rubbish) – 5 (mint) |
| `stock` | INT | Quantity available |
| `oem_number` | VARCHAR(100) NULL | Original part number |
| `replacement_number` | VARCHAR(100) NULL | Cross-reference number |
| `visible` | TINYINT | 0 = private collection |
| `visible_private` | TINYINT | 1 = incrowd-only |
| `for_sale` | TINYINT | 0 = display only |

### connection.php template

```php
<?php
if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', true);
require_once __DIR__ . '/config.php';
$CarpartsConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$CarpartsConnection) die('Database connection failed: ' . mysqli_connect_error());
$CarpartsConnection->set_charset('utf8mb4');
```

## Setup

1. Copy files to web server document root.
2. Create a MySQL/MariaDB database.
3. Set `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` in `config.php`.
4. Create `connection.php` from the template above (gitignored).
5. Ensure `parts/` is writable by the web server (`chmod 755 parts/` or equivalent).
6. Navigate to the site — all tables are auto-created on first visit.
7. Log in via `?navigate=secureadmin`. Create an admin user directly in the `USERS` table with `isadmin=1` and a bcrypt-hashed password, or use `insertuser` once logged in.

## Security Notes

- CSRF tokens on every POST form
- Prepared statements for all parameterised queries
- Passwords: bcrypt via `password_hash()` / `password_verify()`
- Session IDs regenerated on login (`session_regenerate_id(true)`)
- Login rate limiting: 5 attempts / 15-minute lockout per IP
- LFI prevention: all pages routed through `$allowed_pages` whitelist in `index.php`
- File uploads: MIME type verified via `finfo`, allowed only jpg/png/gif/webp, max 1.5 MB

## Theme System

Themes are stored in the `THEMES` table as JSON blobs of CSS custom properties.  
Resolution order per request: user cookie `snldb_theme` → site-active theme → `style.css` defaults.

Public themes appear in the floating 🎨 picker (bottom-right). Themes with `is_dark=1` switch image blend-mode to `screen` on dark backgrounds.

Admin: `?navigate=themeadmin` — create, edit, activate, delete, toggle public visibility.

### Built-in themes (seeded automatically)

| Name | Style |
|---|---|
| Classic Gray | Original blue-grey palette, 4 px radius buttons |
| Unseen Studio | Warm beige/terracotta, pill buttons |
