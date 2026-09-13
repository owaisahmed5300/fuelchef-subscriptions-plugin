# Admin scheduling layer: design

**Status:** draft — pending your review.
**Scope:** the data layer, service layer, and admin UI behind the two screens in
`plugin/templates/admin/html/` (`global-settings.html`, `schedules.html`), plus the
generic Entity/Repository abstraction the rest of the plugin will build on.

## Goals

1. Register a **FuelChef** top-level admin menu (new — none exists yet) with **Global
   Settings** and **Schedules** as its first two submenus.
2. Give those two screens working, persisted data instead of the mockups' in-memory JS
   arrays: global blackout dates, per-store cutoff window, named schedules each with a
   weekly availability table, local blackout dates, and destination assignments.
3. Establish a generic, reusable **Controller → Service → Repository → Entity**
   abstraction (this repo's first), so the next admin page/table reuses it rather than
   reinventing it.
4. Keep PHPStan level 10 and PHPCS clean throughout; unit-test Repositories and
   Services; do not unit-test Controllers (view/glue code — see "Testing").

## Out of scope (explicitly deferred)

- Any admin screen beyond these two.
- Resolving real WooCommerce shipping-zone/pickup-location data — `Schedule_Destination`
  stores a type + a key; a later pass wires the Controller's dropdown data to WooCommerce's
  actual zones. This spec only needs storage + assignment to be correct.
- Any change to the plugin's *customer-facing* behaviour (checkout, recurring orders).
  This is admin configuration only.
- Pixel-perfect parity with the mockup CSS — the visual language (cards, tabs, calendar,
  pills) is kept, but it becomes server-rendered PHP + enqueued assets, not a byte-for-byte
  port of static HTML.

## Data layer

### Tables

All four are owned by this plugin (`{$wpdb->prefix}fcs_<name>`, via the existing
`Database\Tables`/`Abstract_Installer` machinery). Every table gets `date_created` and
`date_updated` `DATETIME` columns (UTC, `Values\DateTime::DATABASE_DATETIME_FORMAT`).

**`schedules`**
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| name | VARCHAR(191) NOT NULL | |

**`schedule_weekdays`** — one row per (schedule, day of week); a schedule always has all
seven, created together so the admin table always has something to render.
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| schedule_id | BIGINT UNSIGNED NOT NULL | FK to `schedules.id`, no DB-level `ON DELETE CASCADE` (see "Cascades") |
| day_of_week | TINYINT UNSIGNED NOT NULL | 0–6, `Values\Day_Of_Week` |
| enabled | TINYINT(1) NOT NULL DEFAULT 0 | |
| start_time | TIME NOT NULL DEFAULT '12:00:00' | |
| UNIQUE (schedule_id, day_of_week) | | one row per day per schedule |
| KEY (schedule_id) | | |

**`blackouts`** — global *and* per-schedule closures in one table; the mockups use the
identical calendar/popover widget for both, so one entity avoids duplicating it.
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| schedule_id | BIGINT UNSIGNED **NULL** | NULL = store-wide closure |
| blackout_date | DATE NOT NULL | `Values\DateTime::DATABASE_DATE_FORMAT` |
| reason | VARCHAR(255) NULL | |
| KEY (schedule_id) | | |
| KEY (blackout_date) | | |

No unique index on `(schedule_id, blackout_date)`: MySQL treats every `NULL` as distinct
for uniqueness, so it would not actually stop two global rows sharing a date. Duplicate
prevention for both cases is `Blackout_Service`'s job (check-then-insert), not the schema's.

**`schedule_destinations`**
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| schedule_id | BIGINT UNSIGNED NOT NULL | |
| destination_type | VARCHAR(20) NOT NULL | `shipping_zone` \| `pickup_location`, `Values\Destination_Type` |
| destination_key | VARCHAR(191) NOT NULL | opaque identifier (e.g. WC zone ID as string) |
| UNIQUE (schedule_id, destination_type, destination_key) | | |
| KEY (schedule_id) | | |

### Entities (`plugin/src/Entities/`)

Plain DTOs: constructor + getters + setters, no behaviour beyond simple invariants.

- `Contracts\Entity` — `id(): ?int` (nullable: an entity built by a caller before insert
  has no id yet).
- `Contracts\Timestamped` — `date_created(): ?DateTime`, `date_updated(): ?DateTime`.
- `Concerns\Has_Timestamps` — trait implementing `Timestamped`: holds the two properties
  and their getters/setters, so an entity `implements Timestamped` and `use
  Has_Timestamps` instead of repeating the properties four times.
- `Entities\Schedule implements Entity, Timestamped` (`use Has_Timestamps`)
- `Entities\Schedule_Weekday implements Entity, Timestamped`
- `Entities\Blackout implements Entity, Timestamped`
- `Entities\Schedule_Destination implements Entity, Timestamped`

### Repositories (`plugin/src/Repositories/`)

- `Abstracts\Abstract_Repository` — constructor takes `wpdb $wpdb` (injected by the
  container, never `global $wpdb` inside the class — this is what lets a test build a
  **real repository instance over a mocked `wpdb`**, the pattern already established for
  this codebase's final classes, instead of inventing a throwaway interface just to mock
  one). Subclasses declare `protected static string $table` and `protected static string
  $cache_group` (typed `static` properties, not `const` — see
  `docs/standards.md`/PHPStan-level-10 notes on why). Provides:
  - `find(int $id): ?Entity`, `find_or_fail(int $id): Entity` (throws
    `Entity_Not_Found_Exception`)
  - `insert(Entity $entity): Entity` (returns it with `id()`/timestamps populated),
    `update(Entity $entity): Entity`, `delete(int $id): void`
  - `hydrate(array $row): Entity` — abstract, implemented per repository, narrowing each
    `mixed` column value explicitly (no blind casts — see the level-10 narrowing notes)
  - cache: `wp_cache_get/set/delete` under the subclass's `$cache_group`, keyed by id;
    each write invalidates that id's cache entry and any list-level cache keys the
    subclass declares (e.g. "all weekdays for schedule N")
- `Schedule_Repository`, `Schedule_Weekday_Repository` (+ `find_by_schedule(int): list`,
  `delete_by_schedule(int): void`), `Blackout_Repository` (+ `find_by_schedule(?int):
  list`, `delete_by_schedule(int): void`), `Schedule_Destination_Repository` (+
  `find_by_schedule(int): list`, `replace_for_schedule(int, list<Schedule_Destination>):
  void`)
- `Exceptions\Entity_Not_Found_Exception`, `Exceptions\Repository_Exception` (wraps an
  unexpected `$wpdb` error — last-error checks, not silent failure)
- `Provider implements ServiceProvider` — registers all four repositories as container
  singletons (autowired: the container resolves their `wpdb` constructor parameter from
  a `wpdb::class` singleton registered once, factory-style, the same way `Requirements`
  is registered in the main plugin file today)

### Cascades

Deleting a `Schedule` is a **service**-level operation: `Schedule_Service::delete()` calls
`Schedule_Destination_Repository::delete_by_schedule()`,
`Schedule_Weekday_Repository::delete_by_schedule()`, and
`Blackout_Repository::delete_by_schedule()`, each invalidating its own cache correctly, and
only then deletes the schedule row. No `ON DELETE CASCADE` at the DB level — that would
delete rows without going through the repository that owns their cache.

## Service layer (`plugin/src/Services/`)

- `Schedule_Service` — create (seeds all 7 `Schedule_Weekday` rows, `enabled = false`,
  `start_time = 12:00:00`), rename, toggle/update a weekday, delete (cascade, above).
- `Blackout_Service` — add/update/remove a blackout date, for either `schedule_id = null`
  (global) or a specific schedule; validates the date via `Values\DateTime::is_valid_date()`
  and duplicate-checks before insert (see "no unique index" above); reason capped at 255
  chars per the schema.
- `Exceptions\Validation_Exception` — thin, business-rule failures a controller turns into
  a user-facing message (invalid date, duplicate date, unknown day-of-week, etc.).
- `Provider implements ServiceProvider` — registers both services as container singletons.
- Destination assignment is handled directly by `Schedule_Destination_Repository::
  replace_for_schedule()` from the controller — there's no validation step yet (WooCommerce
  zone/location resolution is out of scope), so a dedicated service adds no behaviour.

## Settings (`plugin/src/Settings/`) — deliberately *not* Entity/Repository

A setting isn't a database row, so it doesn't go through the abstraction above.

- `Values\Cutoff_Unit` — `HOURS`/`DAYS` constants + label helpers, same shape as
  `Values\Day_Of_Week`.
- `Global_Settings` — value object: `cutoff_amount: int`, `cutoff_unit: Cutoff_Unit`.
- `Settings_Store` — wraps `get_option`/`update_option` under one option key
  (`fuelchef_subscriptions_global_settings`, alongside `Installer::OPTION_KEY`'s
  convention), returning/accepting a `Global_Settings` instance. No custom `wp_cache`
  layer — WordPress's own options cache already covers this.

## Admin layer (`plugin/src/Admin/`)

- `Menu` — hooks `admin_menu`; adds the **FuelChef** top-level menu (slug `fuelchef`) and,
  under it, **Global Settings** (slug `fuelchef-global-settings`) and **Schedules** (slug
  `fuelchef-schedules`) as the first two submenus.
- `Controllers\Global_Settings_Controller` — renders the Global Settings page; registers
  `wp_ajax_fcs_save_cutoff_settings`, `wp_ajax_fcs_save_blackout`,
  `wp_ajax_fcs_delete_blackout` (global, `schedule_id = null`), each nonce-checked and
  capability-checked (`manage_woocommerce` or `manage_options` — confirmed at
  implementation time against how the rest of the plugin gates admin actions).
- `Controllers\Schedules_Controller` — renders the Schedules page; registers
  `wp_ajax_fcs_save_schedule`, `wp_ajax_fcs_delete_schedule`,
  `wp_ajax_fcs_save_schedule_weekday`, `wp_ajax_fcs_save_blackout` /
  `wp_ajax_fcs_delete_blackout` (schedule-scoped), `wp_ajax_fcs_save_schedule_destinations`.
- `Assets` — enqueues the CSS/JS only on these two admin screens.
- `Provider implements ServiceProvider` — registers the above, hooks `admin_menu` in
  `boot()`.
- Ajax over REST, per your instruction — ordinary WordPress admin-ajax actions, not the
  REST API.
- The static mockups become real PHP view templates under `plugin/templates/admin/`
  (`global-settings.php`, `schedules.php`, partials for the calendar/popover shared by
  both), fed server-side data instead of hardcoded JS arrays; `plugin/assets/admin/`
  holds the adapted CSS/JS (talking to the new `wp_ajax_*` actions instead of an
  in-memory array).

## Container wiring

`Container::instance()` gains three `$container->provider(new X\Provider())` calls
(Repositories, Services, Admin), plus the one-time `wpdb::class` singleton registration
each repository's autowired constructor depends on.

## Testing

- **Repositories**: real instance constructed with a Mockery mock of `wpdb`; assert query
  shape (`$wpdb->prepare`/`query`/`get_row`/`get_results` calls) and `wp_cache_*` read,
  write and invalidation via Brain Monkey. Mutation-checked per this repo's existing rule
  (break the code, watch the test fail) before being committed.
- **Services**: real service constructed with **real repositories** (each over a mocked
  `wpdb`) — never a mock of one of this plugin's own final classes. Covers cascade
  ordering, validation, duplicate-blackout rejection, weekday seeding on create.
- **Controllers are not unit-tested.** They are view/glue: pull request data, call a
  service, render a template or send a JSON response. `docs/guidelines/03-testing.md` gets
  a new line saying this explicitly, so it stops being a judgment call for the next screen.
- Manual verification of the rendered admin screens over HTTP (curl + cookie jar against
  the local Docker site), matching how this repo already verifies admin UI.

## Docs to update in the same PRs that change the behaviour they describe

- `docs/technical/data-layer.md` (new) — the Entity/Repository/Service pattern, how
  caching and cascades work, and what a new table needs to plug in.
- `docs/guidelines/03-testing.md` — add "Controllers are not unit-tested."
- `AGENTS.md` / `docs/architecture.md` — a short pointer to `docs/technical/` existing,
  if not already obvious from `docs/README.md`'s existing table (it already lists the
  `technical/` folder; no change needed there unless review disagrees).

## Branch/PR sequence (stacked, one concern per branch)

1. `feat/scheduling-data-layer` (base `main`, itself based on whichever of #1/#2 above
   merges first) — Contracts, Concerns, Abstract_Repository, repository exceptions,
   `Tables`/`Installer` schema for all four tables, all four entities, all four
   repositories + Provider, unit tests for all four repositories.
2. `feat/scheduling-services` (base: branch 1) — `Schedule_Service`, `Blackout_Service` +
   Provider, `Settings\Global_Settings`/`Settings_Store`, `Values\Cutoff_Unit`/
   `Destination_Type`, unit tests for both services, `docs/technical/data-layer.md`,
   `03-testing.md` update.
3. `feat/admin-scheduling-menu` (base: branch 2) — `Admin\Menu`/`Controllers`/`Assets`/
   `Provider`, templates, adapted CSS/JS, `Container.php` wiring, manual HTTP verification.

Each branch stands on its own PR, stacked on the previous (GitHub retargets automatically
as each merges), matching how this repo already stacks related work.
