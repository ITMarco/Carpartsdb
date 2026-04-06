# Supradatabase.nl — Nederlandse Supra Registry

A PHP-based community registry for Toyota Supra vehicles in the Netherlands. Owners can register their car, upload photos, and browse the full database of registered Supras.

## Features

- **License plate search** — look up any registered Supra by its Dutch license plate; dashes optional (enter `12ABCD` or `12-AB-CD`)
- **Per-car profiles** — dedicated page per car with specs, owner info, and a photo slideshow
- **Custom & free-text search** — filter by model, engine, colour, or any other field
- **Statistieken** — most viewed cars, recently added/modified, recent uploads
- **RDW integration** — live lookup of official Dutch vehicle registration data via the RDW Open Data API
- **Photo gallery** — site-wide gallery and per-car image slideshows stored under `cars/<LICENSE>/slides/`
- **Browse with thumbnails** — browse page shows the most recent photo for each car
- **Occasies** — classifieds section for Supras listed for sale
- **Admin panel** — authenticated admins can add, edit, and delete registry entries; two-column layout
- **User accounts** — owners can log in with their license plate and manage their own car's page; self-service password change built in
- **Image upload** — owners/admins can upload photos directly through the web interface
- **Statistics** — homepage shows monthly search counts, session counts, cars added, and images added
- **Supra van de maand** — admin can highlight a featured car on the homepage with photo and caption
- **Dynamic homepage news** — admin-managed news items on the homepage (`HOME_NEWS` table, `homenews` admin page); alternating background colours per item
- **Comments system** — visitors can leave comments on car pages; admin moderation via `commentadmin` with new-comment badge in the footer
- **Contribute flow** — 3-step wizard (find → confirm → upload) with RDW data pre-fill for new cars
- **OpenGraph tags** — per-car pages generate social preview cards when shared on social media
- **Hamburger menu** — collapsible navigation menu in the header
- **Theme system** — CSS custom property theming; admin can create, edit, activate, and publish templates; users can pick their own theme via a floating picker (cookie-based); `is_dark` flag switches image blend mode between `multiply` (light) and `screen` (dark themes)

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.0 (procedural) |
| Database | MySQL / MariaDB via MySQLi |
| Frontend | HTML, CSS, vanilla JavaScript |
| External API | RDW Open Data (`opendata.rdw.nl`) |

## Project Structure

```
index.php              # Main entry point and router
config.php             # Database credentials and app constants
connection.php         # MySQLi connection bootstrap (gitignored — contains credentials)
session_manager.php    # Session handling (timeout, fixation prevention, CSRF)
login_helper.php       # DB-backed rate limiting for login attempts
ip_whitelist_helper.php # Trusted IPs that bypass rate limiting and stats
car_stats_helper.php   # Per-car view/edit statistics logger (session-deduped)
settings_helper.php    # Key-value settings store (SETTINGS table)
stats_helper.php       # Daily statistics helpers (STATS_DAILY table)
theme_helper.php       # Theme system: THEMES table, CSS variable injection, per-user cookie picker
photo_recent_helper.php # Ring-buffer for recently uploaded photos
image_helper.php       # Image processing utilities
bolgallery.php         # Thumbnail gallery generator (caches to bolgallerycars/)
export_database.php    # Database export utility
secure.php             # Standalone login form (not routed via index.php)
engine/                # Site-wide templates
  header.engine.php    # HTML <head>, CSS injection, theme resolution (cookie → active → defaults)
  body_top.engine.php  # Page wrapper with hamburger menu
  body_bottom.engine.php # Footer, admin login link, floating theme picker widget
  style.css            # Stylesheet — fully CSS-variable-driven, no hardcoded colours
pages/                 # Individual page includes (routed via index.php whitelist)
  home.php             # Homepage with stats widget (responsive horizontal layout on mobile)
  search.php           # License plate search
  customsearch.php     # Advanced/filtered search
  freetextsearch.php   # Free-text search
  includesearch.php    # Per-car profile page (also logs views to CAR_VIEWS)
  rdwcheck.php         # RDW Open Data lookup
  rdwimport.php        # Bulk import new plates from RDW
  rdwupdate.php        # Check existing plates against RDW for changes
  gallery.php          # Site-wide photo gallery
  occasies.php         # Classifieds / for-sale listings
  topten.php           # Statistieken: recent photos, modified, added, most viewed
  adminpanel.php       # Admin dashboard (two-column layout)
  adminedit.php        # Admin car editor (search form)
  adminedit2.php       # Admin car editor (edit form)
  procesadminedit.php  # Admin car editor (save — also logs edits to CAR_VIEWS)
  insertnew.php        # Add a new Supra
  importfromurl.php    # Import from Marktplaats URL
  uploadimage.php      # Image uploader
  carstats.php         # Admin: per-car view/edit statistics
  ipwhitelist.php      # Admin: manage trusted IP addresses
  themeadmin.php       # Admin: theme & colour manager (create/edit/activate/publish templates)
  homenews.php         # Admin: manage homepage news items (HOME_NEWS table)
  commentadmin.php     # Admin: moderate comments (approve/hide/delete)
  contribute.php       # Public: 3-step contribute flow (find car → confirm → upload photo)
  secureadmin.php      # Admin login form
  insertuser.php       # Add user
  processinsertuser.php # Save new user
  edituser.php         # Edit user form
  processedituser.php  # Save user edits
  about.php            # About page
  links.php            # Links page
  geschiedenis.php     # History page
  logout.php           # Session logout
  ...
cars/                  # Per-car data and images
  <LICENSE>/
    slides/            # Car photos
    bolGallery/        # Auto-generated thumbnails
bolgallerycars/        # Cached gallery HTML pages
data/                  # Static data files (menu, titles, etc.)
images/                # Site-wide images and header backgrounds
```

## Security

- Login rate-limited: max 5 attempts per IP, 15-minute lockout (stored in `LOGIN_ATTEMPTS` table)
- Trusted IPs can be whitelisted via admin panel — they bypass rate limiting and are excluded from statistics
- Passwords stored as bcrypt hashes; legacy plaintext passwords are upgraded on first login
- CSRF tokens required on all POST forms
- Page routing uses a whitelist to prevent Local File Inclusion (LFI) attacks
- Prepared statements used for all parameterised database queries
- Session IDs regenerated on login to prevent session fixation

## Theme System

Themes are stored in the `THEMES` table as JSON blobs of CSS custom properties. On every page load, `theme_helper.php` resolves the active theme in this order:

1. User cookie `snldb_theme` (if set and the theme is public or active)
2. Site-wide active theme (set by admin)
3. Hardcoded defaults in `style.css` `:root { }` block

### CSS variables

| Variable | Purpose |
|---|---|
| `--color-body-bg` | Page background |
| `--color-text` | Body text |
| `--color-link` | Link colour |
| `--color-container-border` | Outer container border |
| `--color-surface` | Content panel background |
| `--color-accent` | Accent colour / headings |
| `--color-accent-dark` | Darker accent (action bars) |
| `--color-nav-bg` | Nav sidebar background |
| `--color-nav-text` | Nav link colour |
| `--color-nav-border` | Nav item divider |
| `--color-nav-hover-bg` | Nav hover background |
| `--color-nav-hover-text` | Nav hover text |
| `--color-input-bg` | Input field background |
| `--color-input-border` | Input field border |
| `--color-content-border` | Content-box border |
| `--color-box-header-bg` | Content-box title strip background |
| `--color-box-header-text` | Content-box title strip text |
| `--color-news-bg-1` | Homepage news item background (odd) |
| `--color-news-bg-2` | Homepage news item background (even) |
| `--btn-bg` | Button background |
| `--btn-text` | Button text |
| `--btn-border` | Button border |
| `--btn-radius` | Button border radius (0px / 4px / 8px / 24px) |

### Built-in themes (seeded automatically)

| Name | Style |
|---|---|
| Classic Gray | Original blue-grey palette, 4px buttons |
| Unseen Studio | Warm beige/terracotta, pill buttons |

### Additional themes (insert via SQL)

Nomadic Tribe, Toyota.com, and Amayama themes are provided as SQL `INSERT` statements and can be added via a DB client. See the Theme Admin page for the picker/editor.

### Admin

`?navigate=themeadmin` — create, edit, activate, delete themes and toggle per-theme public visibility (public themes appear in the floating user picker).

### Dark theme flag

Each theme has an `is_dark` boolean. When set, the `snldb_theme_dark=1` cookie is written alongside `snldb_theme` when the user picks the theme. On `home.php` this switches image `mix-blend-mode` from `multiply` (blends on light backgrounds) to `screen` (blends on dark backgrounds). The active theme's `is_dark` is used as fallback when no cookie is present.

### User picker

A floating 🎨 button appears bottom-right when at least one theme is marked public. Clicking a theme sets 1-year cookies (`snldb_theme`, `snldb_theme_dark`) and reloads. "↩ Site standaard" resets to the site default.

---

## Setup

1. Copy all files to your web server document root.
2. Create a MySQL/MariaDB database.
3. Run all SQL statements from the **Database Schema** section below.
4. Create `connection.php` in the web root (see template below).
5. Edit `config.php` with your database host, username, password, and database name.
6. Ensure `cars/` and `bolgallerycars/` are writable by the web server.
7. Insert one row into `HITS` with `key='1'` to initialise the search counter.
8. Insert at least one admin user into `PASSWRDS`.

### connection.php template

```php
<?php
if (!defined('SNLDBCARPARTS_ACCESS')) define('SNLDBCARPARTS_ACCESS', true);
require_once __DIR__ . '/config.php';
$CarpartsConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$CarpartsConnection) die('Database connection failed: ' . mysqli_connect_error());
$CarpartsConnection->set_charset('utf8mb4');
```

---

## Database Schema

Ten tables in total. Four are defined below as static SQL; six are auto-created by their PHP helper on first use.

### Core tables (run these manually)

```sql
-- ─── HITS ─────────────────────────────────────────────────────────────────────
-- Legacy search counter. Must contain exactly one row with key='1'.
CREATE TABLE `HITS` (
  `key`        varchar(10) NOT NULL,
  `searches`   varchar(10) NOT NULL DEFAULT '0',
  `searchhits` varchar(10) NOT NULL DEFAULT '0',
  `countstart` text        NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Required seed row:
INSERT INTO `HITS` (`key`, `searches`, `searchhits`, `countstart`)
VALUES ('1', '0', '0', NOW());


-- ─── STATS_DAILY ──────────────────────────────────────────────────────────────
-- Daily aggregated counters (sessions, searches, supras added, images added).
CREATE TABLE `STATS_DAILY` (
  `stat_date`    date             NOT NULL,
  `sessions`     int(10) UNSIGNED DEFAULT 0,
  `searches`     int(10) UNSIGNED DEFAULT 0,
  `supras_added` int(10) UNSIGNED DEFAULT 0,
  `images_added` int(10) UNSIGNED DEFAULT 0,
  PRIMARY KEY (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

### Auto-created tables (created by PHP helpers on first use)

```sql
-- ─── SETTINGS ─────────────────────────────────────────────────────────────────
-- Key-value store for application settings. Auto-created by settings_helper.php.
CREATE TABLE IF NOT EXISTS `SETTINGS` (
  `setting_key`   VARCHAR(64)  NOT NULL PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── THEMES ───────────────────────────────────────────────────────────────────
-- CSS custom property theme templates. Auto-created by theme_helper.php.
-- Seeded with Classic Gray (active) and Unseen Studio on first use.
CREATE TABLE IF NOT EXISTS `THEMES` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(64)  NOT NULL,
  `vars`       TEXT         NOT NULL,   -- JSON object of CSS variable name → value
  `is_active`  TINYINT(1)   DEFAULT 0,  -- exactly one row should be active at a time
  `is_public`  TINYINT(1)   DEFAULT 0,  -- public themes appear in the user picker
  `is_dark`    TINYINT(1)   DEFAULT 0,  -- switches mix-blend-mode to 'screen' on images
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP
);


-- ─── PHOTO_RECENT ─────────────────────────────────────────────────────────────
-- Ring buffer of the 24 most recently uploaded photos. Auto-created by photo_recent_helper.php.
CREATE TABLE IF NOT EXISTS `PHOTO_RECENT` (
  `slot`        TINYINT UNSIGNED NOT NULL,
  `license`     VARCHAR(20)      DEFAULT NULL,
  `filename`    VARCHAR(255)     DEFAULT NULL,
  `uploaded_at` DATETIME         DEFAULT NULL,
  PRIMARY KEY (`slot`)
);


-- ─── CAR_VIEWS ────────────────────────────────────────────────────────────────
-- Per-car view and edit log. Auto-created by car_stats_helper.php.
-- Whitelisted IPs (see IP_WHITELIST) are never inserted here.
-- Each session counts a specific car only once per event_type (session-deduped).
CREATE TABLE IF NOT EXISTS `CAR_VIEWS` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `license`    VARCHAR(20)           NOT NULL,
  `event_type` ENUM('view','edit')   NOT NULL DEFAULT 'view',
  `ip`         VARCHAR(45)           NOT NULL,
  `user_agent` VARCHAR(255)          NOT NULL DEFAULT '',
  `view_time`  INT                   NOT NULL,
  INDEX `idx_license` (`license`),
  INDEX `idx_time`    (`view_time`),
  INDEX `idx_type`    (`event_type`)
);


-- ─── LOGIN_ATTEMPTS ───────────────────────────────────────────────────────────
-- Failed login attempts per IP for rate limiting. Auto-created by login_helper.php.
CREATE TABLE IF NOT EXISTS `LOGIN_ATTEMPTS` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `ip`           VARCHAR(45) NOT NULL,
  `attempt_time` INT         NOT NULL,
  INDEX `idx_ip_time` (`ip`, `attempt_time`)
);


-- ─── IP_WHITELIST ─────────────────────────────────────────────────────────────
-- Trusted IP addresses. Auto-created by ip_whitelist_helper.php.
-- These IPs bypass login rate limiting and are excluded from CAR_VIEWS statistics.
CREATE TABLE IF NOT EXISTS `IP_WHITELIST` (
  `ip`       VARCHAR(45)  NOT NULL PRIMARY KEY,
  `label`    VARCHAR(100) NOT NULL DEFAULT '',
  `added_at` DATETIME     NOT NULL
);


-- ─── HOME_NEWS ─────────────────────────────────────────────────────────────────
-- Homepage news items managed via admin page homenews.php. Auto-created on first use.
CREATE TABLE IF NOT EXISTS `HOME_NEWS` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(200) NOT NULL,
  `body`       TEXT         NOT NULL,
  `news_date`  DATE         NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `visible`    TINYINT(1)   NOT NULL DEFAULT 1
);

