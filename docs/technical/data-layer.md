# Data layer

Entities, repositories, services, settings and the abstraction under them. Design
rationale is in
[`../superpowers/specs/2026-09-13-admin-scheduling-layer-design.md`](../superpowers/specs/2026-09-13-admin-scheduling-layer-design.md);
this is the "how do I add a table / a service / a setting" reference.

## Layering

```
Controller -> Service -> Repository -> Entity
```

Controllers handle a request and call a service. Services hold business logic and call
repositories. Repositories are the only thing that touches `$wpdb`, and they return
entities, never arrays. Entities are plain DTOs: a constructor, getters, setters, no
business logic.

## Adding a table

1. Add the bare table name to `Database\Tables`, and a `CREATE TABLE` method on
   `Database\Installer` (added as a closure in its `schemas()` method — see the existing
   four for the `PRIMARY KEY  (id)` double-space dbDelta quirk).
2. Add an entity under `Entities\` implementing `Contracts\Entity`; add
   `Contracts\Timestamped` (`use Concerns\Has_Timestamps`) if it has `date_created`/
   `date_updated` columns, which every table here does so far.
3. Add a repository under `Repositories\` extending
   `Repositories\Abstracts\Abstract_Repository<TheEntity>`. It supplies `$table`,
   `$cache_group`, `hydrate()` and `dehydrate()`; `find()`/`insert()`/`update()`/
   `delete()` come from the base, and fire the actions described in "Hooks" below with no
   extra work. Register it in `Repositories\Provider`.
4. Any query beyond `find()` (e.g. `find_by_schedule()`) is the concrete repository's own
   method, caching its own key and clearing it in an `invalidate_related()` override. A
   query whose parameters vary too widely to cache usefully (e.g. a date range) skips
   `wp_cache_*` entirely — see `Blackout_Repository::find_by_schedule_between()` — and runs
   its result through an `apply_filters()` call instead, so other code can extend it.

## Caching

Every read goes through `wp_cache_get`/`wp_cache_set` under the repository's own cache
group. A repository invalidates its own row cache on write; a repository maintaining a
list-level cache (e.g. "every weekday for schedule 4") clears that key too, via
`invalidate_related()`. Cascading across repositories (e.g. deleting a schedule also
clearing its weekdays and blackouts) is the *service's* job, calling each repository's own
`delete()` in turn — never a DB-level `ON DELETE CASCADE`, which would delete rows without
going through the repository that owns their cache.

## Hooks

Every repository gets three actions for free from `Abstract_Repository`: a successful
`insert()`, `update()` or `delete()` fires `fuelchef_subscriptions/{table}/created`,
`.../updated` or `.../deleted`, named after the table (`schedules`, `blackouts`, and so
on) — `deleted` only fires when a row actually existed to remove. Nothing to add when
writing a new repository; it comes from extending the base class.

A repository method with its own extension point (see `find_by_schedule_between()` above)
runs its result through `apply_filters( 'fuelchef_subscriptions/{area}/{query}', ... )`
before returning it, and narrows the filtered value back to the declared return type
rather than trusting it — a filter can hand back anything.

## Services

A service holds business logic and orchestrates repositories; it never touches `$wpdb`
directly. Add one only when there is an actual business rule or cross-repository
operation to hold - a single repository call with no validation belongs directly in the
controller instead (see `Schedule_Destination_Repository::replace_for_schedule()`, called
straight from the controller for exactly this reason).

- **Validation failures** are `Services\Exceptions\Validation_Exception` - one class,
  reused for every business-rule rejection (invalid date, duplicate date, blank name,
  unknown day of week), each with its own named static factory
  (`Validation_Exception::for_invalid_date()`). Never add a new exception class narrowed
  to one field or entity.
- **A cascading delete** (e.g. deleting a schedule also clearing its weekdays, blackouts
  and destinations) calls each repository's own `delete()`/`delete_by_schedule()` in
  order, never a DB-level `ON DELETE CASCADE` - see "Caching" above for why.
- **Register a service** as a container singleton in `Services\Provider::register()`,
  wired to its repository dependencies the same way `Repositories\Provider` wires a
  repository to `wpdb`/`Clock`.
- **Testing**: a real service constructed with real repositories (each over its own
  mocked `wpdb`) - never a mock of one of this plugin's own final classes. Covers
  validation, cascade ordering and any seeding behaviour (see
  `Schedule_Service_Test::test_create_seeds_all_seven_weekdays_disabled_at_noon`).

## Settings

A setting is not a database row, so `plugin/src/Settings/` deliberately does not go
through the Entity/Repository/Service abstraction above.

- `Settings\Global_Settings` - an immutable value object; its constructor validates every
  field (a valid `Cutoff_Unit`, a 0-100 discount, a valid `Subscribe_Applicability`) and
  throws `InvalidArgumentException` on the same "caller's own bug" basis as an entity
  constructor does.
- `Settings\Settings_Store` wraps `get_option()`/`update_option()` under one option key.
  Its `get()` never throws: a missing or no-longer-valid stored value (an old plugin
  version, hand-edited option data) falls back to a default instead of breaking the
  settings screen. `save()` fires `fuelchef_subscriptions/settings/updated`. No custom
  `wp_cache` layer - WordPress's own options cache already covers this.
- A new setting gets a field on `Global_Settings` (with its own validation branch), a
  default in `Settings_Store`, and - if its valid values are a fixed set - a
  `Values\*` enum-shaped class alongside `Cutoff_Unit`/`Subscribe_Applicability`.

## Admin

`plugin/src/Admin/` is the `Controller` in the layering diagram above, plus the menu and
asset registration around it.

- A controller's `render()` builds its page's data from repositories/services and passes
  it to `Templating\Renderer::render()` (shared with the frontend checkout, which will use
  the same class). Data the page's own **script** needs (the calendar, the weekly-hours
  table, the destination list) goes through `wp_localize_script()` instead of an inline
  `<script>` block with embedded PHP - simpler, and avoids fighting WPCS's rules on PHP
  tags mixed into HTML.
- `Utils\Input::string()` narrows a `$_POST`/`$_GET` value before it reaches `absint()`/
  `sanitize_text_field()` - PHPStan's strict rules reject passing a superglobal's `mixed`
  value to either directly.
- **Match an admin screen on `$_GET['page']`, not `$hook_suffix`.** A submenu's hook
  suffix is derived from its parent slug in a way that's easy to guess wrong (it was,
  here - see `Admin\Assets::enqueue()`); `page` is exactly the slug the screen was
  registered under.
- A controller's own ajax actions are registered in its `register()` method, called from
  `Admin\Provider::boot()` - not gated to when its own screen is being viewed, since an
  ajax request to `admin-ajax.php` carries no "current screen". Two screens sharing one
  underlying resource (the Schedules screen's local blackouts and the Global Settings
  screen's store-wide ones) share one registered action rather than each registering the
  same `wp_ajax_*` hook, which would run both callbacks on every request.

## Row values are `mixed` — narrow them explicitly

`$wpdb` returns every column as `string` or `null`; `Utils\Row_Caster` has the narrowing
helpers (`string()`, `nullable_string()`, `int()`, `nullable_int()`, `bool()`) a
`hydrate()` uses instead of casting a `mixed` value directly, which PHPStan's strict
rules (level 10) reject.

## Testing

A repository test builds a **real repository instance over a mocked `wpdb`** (see
`tests/Unit/Repositories/Repository_TestCase.php`) — never a mock of the repository
itself. `wpdb->prepare()` is stubbed to interpolate placeholders literally; the point of
these tests is the repository's own logic (hydration, caching, cascades), not
WordPress's SQL escaping.

Controllers are not unit-tested — they're view/glue code (pull request data, call a
service, render a template or send a response). Only repositories and services get unit
tests.
