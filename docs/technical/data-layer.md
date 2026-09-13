# Data layer

Entities, repositories and the abstraction under them. Design rationale is in
[`../superpowers/specs/2026-09-13-admin-scheduling-layer-design.md`](../superpowers/specs/2026-09-13-admin-scheduling-layer-design.md);
this is the "how do I add a table" reference.

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
   `Database\Installer` (registered in its `$schemas` list — see the existing four for
   the `PRIMARY KEY  (id)` double-space dbDelta quirk).
2. Add an entity under `Entities\` implementing `Contracts\Entity`; add
   `Contracts\Timestamped` (`use Concerns\Has_Timestamps`) if it has `date_created`/
   `date_updated` columns, which every table here does so far.
3. Add a repository under `Repositories\` extending
   `Repositories\Abstracts\Abstract_Repository<TheEntity>`. It supplies `$table`,
   `$cache_group`, `hydrate()` and `dehydrate()`; `find()`/`insert()`/`update()`/
   `delete()` come from the base. Register it in `Repositories\Provider`.
4. Any query beyond `find()` (e.g. `find_by_schedule()`) is the concrete repository's own
   method, caching its own key and clearing it in an `invalidate_related()` override.

## Caching

Every read goes through `wp_cache_get`/`wp_cache_set` under the repository's own cache
group. A repository invalidates its own row cache on write; a repository maintaining a
list-level cache (e.g. "every weekday for schedule 4") clears that key too, via
`invalidate_related()`. Cascading across repositories (e.g. deleting a schedule also
clearing its weekdays and blackouts) is the *service's* job, calling each repository's own
`delete()` in turn — never a DB-level `ON DELETE CASCADE`, which would delete rows without
going through the repository that owns their cache.

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
