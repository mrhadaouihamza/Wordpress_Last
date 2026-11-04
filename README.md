# Wordpresso Store and Plugin Update Server

Wordpresso combines a public storefront for WordPress plugins, themes, and
subscription plans with an admin panel and private plugin update service. The
server URL is configured for the deployment. Customers can create accounts,
browse products, place orders, and manage purchases. They can also connect
eligible WordPress sites using the **Wordpresso Auto Updater** plugin; available
plugin updates appear in the normal WordPress dashboard and can be installed
manually or automatically.

```
┌──────────────────────────┐        ┌─────────────────────────────────┐
│  Customer WordPress site │        │  Wordpresso storefront          │
│                          │        │                                 │
│  wordpresso-auto-updater │  HMAC  │  Update API (/api/v1/*)         │
│  plugin:                 │───────▶│  · register / updates / package │
│  · scheduled checks      │ signed │                                 │
│  · native WP updates     │◀───────│  Plugin catalog + versions      │
│  · authenticated         │  ZIP   │  Protected package storage      │
│    package download      │        │  Store / checkout / plans       │
│                          │        │  Admin dashboard + logs         │
└──────────────────────────┘        └─────────────────────────────────┘
```

## Repository layout

```
server/                          The update server (plain PHP 8.1+, no dependencies)
├── public/                      Web docroot — only index.php + tiny assets
├── app/Core/                    Router, DB, sessions, CSRF, views, migrations, crypto
├── app/Models/                  Data access (plugins, versions, customers, websites…)
├── app/Services/                API auth, entitlements, package validation/storage, updates
├── app/Controllers/{Api,Admin,Storefront}
├── templates/                   Store + admin themes
├── migrations/                  Forward-only SQL migrations
├── config/config.php            All settings (env-overridable)
├── storage/                     Local DB, packages, logs — NEVER web-accessible
└── bin/                         CLI: migrate, create-admin, query…

client-plugin/wordpresso-auto-updater/   Installable WordPress plugin (PHP 8.0+)

tests/                           Dependency-free test suite + E2E client
docs/                            API, security, deployment documentation
```

## Quick start (local evaluation)

```bash
cd server
php bin/migrate.php              # create the SQLite schema
php bin/create-admin.php you@example.com   # prints a one-time password
WORDPRESSO_SESSION_SECURE=0 php -S 127.0.0.1:8080 -t public public/dev-router.php
```

Open `http://127.0.0.1:8080` (storefront) and `/admin` (dashboard).

## Storefront and admin panel

The public website includes a home page, a combined plugin and theme catalog,
pricing, cart, checkout, account registration, sign-in, and customer account
pages. The shared responsive storefront layout keeps its header, content, and
footer aligned. The product catalog is not seeded with demo products; add the
products you intend to sell in the admin panel.

The admin panel includes dashboard statistics, products, orders, subscription
plans, **Store settings** for the site title, tagline, descriptions, support
email, and footer text, separate **Payments** settings for wallet/RPC details,
customers, connected websites, and update logs with automated and manual pruning options. Admins can review and delete old database logs from **Admin → Logs** or schedule maintenance with `bin/prune-logs.php`. Admins can confirm or reject
pending orders from Orders; confirming activates the purchased access. From a
customer detail page, admins can grant a published subscription plan and its
included products. The product editor has styled in-field examples and compatibility fields for the
plugin basename, minimum WordPress version, tested WordPress version, and
minimum PHP version.

## Store, checkout, and subscriptions

Store listings are managed in **Admin → Products**. Create plugin listings
linked to an updater plugin or theme listings with a ZIP package. Plugins and
themes appear in the same storefront catalog. Manage purchasable plans in
**Admin → Subscriptions**: set a price and access period, choose the active
plugins and themes the plan includes, set its connected website limit, then
publish it on the pricing page.
Plans are offers customers buy, not subscriptions manually assigned to each
customer. After payment is verified, Wordpresso creates customer access for the
selected products. Plugin entitlements permit updater access; included themes
are available for download.

Set the receiving address, USDT contract, and HTTPS BNB Smart Chain RPC in
**Admin → Payments**. Checkout accepts USDT on BNB Smart Chain and verifies the
transaction receipt, token transfer, destination, amount, and 12 block
confirmations before activating access. Access periods do not auto-renew;
customers buy another term to extend access. A customer can cancel an order
while it is still pending from the order page or the checkout screen; cancelled
orders cannot be paid or activated, and the cart items are kept so the customer
can try again. Product and release packages are stored in protected storage and
downloads require a purchase or active access.

## Publishing releases and updates

For a plugin, use **Admin → Products → Upload / publish releases** to open its
release page. Upload a ZIP and version number. Wordpresso validates the ZIP,
expected plugin main file, and version header before it can be published.
Publishing makes that version available to eligible connected sites. Previous
versions can be re-published or withdrawn. Product compatibility requirements
can be set in the product editor or updater plugin record.

For a theme, edit its product and replace the ZIP to update customer downloads.
The current WordPress updater supports plugin updates only; it does not offer
theme auto-updates.

The updater checks on a schedule (twice daily by default) and supports manual
checks and automatic plugin updates when enabled. The server URL can be changed
in **Wordpresso Settings** in WordPress. Connection status is shown there, and
the separate **Updates** page lists plugin update actions.

Connected WordPress website credentials can be permanently removed from
**Admin → Websites** or from a customer's **My account → Websites** page. A
direct plugin purchase allows one connected website. Active subscription
purchases use the highest connected-website limit set on their plan. Removing a
website deletes its API key and encrypted secret while retaining audit logs.

For a fresh cPanel install, create the MariaDB/MySQL database and user, upload
the latest `cpanel-deploy.zip`, set database credentials and a private installer
key (32+ characters) in `public_html/config/config.local.php`, then open
`https://your-domain.example/install/`. The GUI installer imports
`database.sql`, creates the first admin, and removes itself after success. For an existing install,
import the refreshed SQL in phpMyAdmin to add tables such as
`store_plan_products`; `CREATE TABLE IF NOT EXISTS` preserves existing data.
Configure the wallet in Admin → Payments and add the store listings. The
production cPanel package uses MariaDB/MySQL only; SQLite is used only by the
optional local development setup and is not included in the cPanel package.

> `dev-router.php` exists **only** for PHP's built-in server. Production uses
> Apache or nginx — see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## How a release flows through the system

1. **Admin adds a plugin** to the catalog with its *technical identifier*
   (plugin basename, e.g. `elementor-pro/elementor-pro.php`). Display names are
   never used for matching.
2. **Admin uploads a ZIP** for a new version. The server validates it (real
   ZIP, main plugin file present, `Version:` header matches, no path
   traversal), computes its SHA-256, and stores it **outside the docroot** as a
   *draft* version.
3. **Admin publishes the version** → it becomes the single *current* version.
   Previous versions remain and can be re-published (rollback) or withdrawn.
4. **Connected sites check for updates** (twice daily + manual). The server
   authenticates the request (HMAC signature + timestamp + single-use nonce),
   verifies the customer's subscription and per-plugin entitlement, checks
   WordPress/PHP compatibility, and returns only eligible updates.
5. The client injects offers into the native `update_plugins` transient —
   updates appear exactly like WordPress.org updates.
6. On **Update Now**, the client intercepts `upgrader_pre_download`, performs
   an authenticated request for a *single-use, 10-minute download token*,
   downloads the ZIP, verifies its SHA-256, and hands the local file to
   WordPress's own `Plugin_Upgrader`.
7. Every check, download, authorization failure and install result is **logged**
   and visible in the dashboard with filters.

## Documentation

| Topic | File |
|---|---|
| API reference (endpoints, signing scheme) | [docs/API.md](docs/API.md) |
| Security model & threat notes | [docs/SECURITY.md](docs/SECURITY.md) |
| Production deployment & operations | [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) |
| Architecture decisions / original plan mapping | [docs/PLAN.md](docs/PLAN.md) |

For cPanel hosting, use the ready-to-upload [cPanel package](cpanel/README.md).
It includes a `public_html/` tree, a GUI setup wizard, protected storage,
and the MariaDB/MySQL schema at
[cpanel/public_html/install/database.sql](cpanel/public_html/install/database.sql).

## Testing

```bash
php tests/run.php                 # 46 unit/integration tests (isolated temp DB)
php tests/check-imports.php       # static missing-import check
# with the dev server running:
php tests/e2e-client.php http://127.0.0.1:8080 <api-key> <api-secret>
```

## Requirements

**Server:** PHP 8.1+ with PDO, `zip`, `openssl`; Apache or nginx. The cPanel
package requires PDO MySQL, `mbstring`, and cURL in addition.
**Client plugin:** WordPress 5.8+, PHP 8.0+.

## Licensing note

Only upload plugin packages that you are legally entitled to redistribute.
The system deliberately contains no mechanism for bypassing third-party
licensing or protection systems.
