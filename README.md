# School Management System (SPA)

A CodeIgniter 4 school management system (admissions, attendance, fees,
exams, employee/HR, transport, academics) with a server-rendered login page
and an AJAX-driven "SPA" shell for the logged-in employee and student
portals.

## Tech stack

- **Backend:** PHP / CodeIgniter 4, MySQL (MariaDB in production)
- **Frontend:** jQuery + a hand-rolled AJAX router (no framework — see below)
- **Auth:** JWT (`app/Filters/JWTAuthFilter.php`), applied globally to every
  route except an explicit skip-list (login, pre-login, forgot-password,
  privacy-policy, public fee receipts)

## ⚠️ Known weak point: the custom JS router

There is **no client-side routing library** in this project. Navigation
inside the logged-in portals is a hand-rolled AJAX router, implemented as
inline `<script>` blocks duplicated almost 1:1 in two view files:

- `app/Views/portal/post-login-employee.php`
- `app/Views/portal/post-login-student.php`

Each file defines its own `navigateTo()`, `AppState`, `pluginConfigs`,
`window.onpopstate`, and Capacitor (mobile) back-button handling — copy-pasted
rather than shared. **This is the most fragile part of the app and the most
common source of "the page didn't load" / "back button doesn't work" /
"double navigation" bugs.** If you're debugging a navigation issue, start
here, not in the PHP controllers.

Known failure modes that have already bitten this router (fixed so far, but
the underlying duplication means new copies of the same bug can still creep
into one file and not the other):

- Browser back button doing nothing on the very first press (the initial
  page load never wrote a `history.pushState`/`replaceState` entry, so the
  first `popstate` had no `event.state` to act on).
- Navigation clicks silently dropped when they happened while another
  request was still in flight (the queued-navigation dequeue code existed
  but was commented out).
- The mobile hardware back button firing twice (Capacitor's `backButton`
  listener was registered in two separate `DOMContentLoaded` blocks in the
  same file).

**If you're picking up router work next:** the real fix is to extract the
router (`navigateTo`, `AppState`, plugin lifecycle, history handling) into a
single shared JS asset under `public/assets/js/` that both portal shells
include, instead of maintaining two copies. Until that refactor happens,
any router fix must be applied to **both** `post-login-employee.php` and
`post-login-student.php`, or the two portals will drift further apart.

## Setup

Copy `env` to `.env` and set at minimum:

- `app.baseURL`
- `database.default.*` (hostname, database, username, password)
- `JWT_SECRET` (required — `JWTAuthFilter` decodes every authenticated
  request with this; there is no fallback)

`index.php` lives in `public/`, not the project root — point your web
server's document root at `public/`.

### Database / migrations

`app/Database/Migrations` previously had no migrations at all even though
the production database has 39 tables — the schema only existed in the
live MySQL instance. A baseline migration
(`2026-09-24-000000_InitialSchema.php`) now reverse-engineers that schema
from a production dump, so a fresh environment can be provisioned with:

```
php spark migrate
```

Add any further schema changes as new migrations on top of that baseline —
don't edit it in place.

### Auth notes

- Passwords are hashed with `password_hash()` on write. Older rows created
  before hashing was introduced are still plaintext; login verifies against
  either format and silently upgrades a plaintext row to a hash on
  successful login (see `BaseController::verifyAndUpgradePassword()`).
- Every route is behind the global `jwt` filter except the explicit
  skip-list in `JWTAuthFilter`. If you add a new route that should be
  public, add its first URI segment there rather than disabling the filter.

## Server requirements

PHP 8.1+, with the `intl`, `mbstring`, `json`, and `mysqlnd` extensions
enabled.

## Repository structure

- `app/Controllers/Web/*ModulePages` — page controllers that render the
  fragments the AJAX router swaps into `#app`
- `app/Controllers/Data` — business logic / data access, called from the
  Web controllers
- `app/Views/portal` — the two SPA shells described above
- `app/Views/pages` — the individual page fragments loaded into the shells
