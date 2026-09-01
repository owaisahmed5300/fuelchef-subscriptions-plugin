# Code quality

PHPCS and PHPStan enforce the mechanical half of this — indentation, naming, types,
docblocks. This is the half a linter cannot check.

How the repository fits together is [`../architecture.md`](../architecture.md).

---

## The over-engineering test

Three questions:

1. Does something in the specification need it **today**?
2. Does WordPress or WooCommerce already do it?
3. What breaks if we leave it out?

If the answer to 3 is "nothing until we are much bigger", leave it out and write down
what the trigger would be. Every layer is something the next developer must learn before
they can change anything.

---

## Write for the reader

Code is read far more often than written, and the next reader is often an agent with no
memory of why.

- **Name for intent.** `$locked_at` beats `$timestamp`. `deliveries_needing_orders()`
  beats `get_data()`. A comment explaining a name means the name is wrong.
- **Short functions with one job.** If you cannot describe it without "and", split it.
- **Guard clauses over nesting.** Handle the exceptional case and return; keep the happy
  path at one indent level. Enforced by the `EarlyExit` sniff.
- **Avoid boolean parameters** that change behaviour. `save( true )` tells the reader
  nothing; two named methods do.
- **No magic values.** A number or string with meaning is a named constant, and the name
  is where the meaning lives.
- **Delete rather than comment out.** Git remembers.

## Types are documentation the compiler checks

- `declare(strict_types=1)` in every file.
- Type every parameter, return and property. `mixed` is a last resort and needs a reason.
- Classes are `final` unless designed for extension — and if extensible, say what a
  subclass may override.
- Use precise PHPStan array shapes: `array<string, array{name: string, min_version: string}>`,
  not `array`. PHPStan runs at level 8 with strict rules; a shape is what makes it useful.
- Never widen a type or add a `@phpstan-ignore` to silence an error. The error is
  usually right. If it genuinely is not, the ignore goes in `phpstan.neon.dist` with a
  comment saying why — see the existing entries.

## Dependencies

- Runtime dependencies go in `plugin/composer.json`; dev tooling in the root one.
- **Import the prefixed namespace** in plugin code — for the DI container that is
  `WPPluginBoilerplate_Deps\WPTechnix\DI\Container`. The unprefixed name resolves against
  the dev tree and then fatals in a release.
- Constructor injection, always. A class that constructs its own collaborators cannot be
  tested, and this is the most common reason a test needs an awkward fixture.
- Register services in `plugin/src/Container.php`. No service locators, no statics
  reaching for globals.

---

## WordPress specifics

These are the ones that turn into security advisories.

| Rule | |
| --- | --- |
| **Escape on output, every time** | `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`. Escape late, at the point of echo — not once on the way in |
| **Sanitise on input** | `sanitize_text_field()`, `absint()`, and friends. Validate that it is one of the values you expect, not merely that it is a string |
| **Never trust `$_GET`/`$_POST`/`$_REQUEST`** | Unslash (`wp_unslash()`), sanitise, validate — in that order |
| **Nonce + capability on every state change** | A nonce proves intent, a capability check proves permission. Neither substitutes for the other |
| **No `GET` changes state** | A link must never delete, charge or mutate. Use a form with a nonce |
| **Always `$wpdb->prepare()`** | Every interpolated value, without exception. Table names come from `$wpdb->prefix`, never from input |
| **WooCommerce data through its CRUD API** | `wc_get_order()` / `wc_get_orders()`, never `WP_Query` or direct SQL — with HPOS the posts table may not hold them |
| **Translate every user-facing string** | With the `wp-plugin-boilerplate` text domain, and a `/* translators: */` comment on every placeholder |
| **Prefix everything global** | Hooks, options, post meta, tables, transients, CSS classes. Two plugins share one namespace |
| **Check ownership before acting** | An endpoint serving a logged-in user verifies the record belongs to them, and returns 404 rather than 403 — a 403 confirms the record exists |

## Errors and failure

- Fail loudly in development, gracefully in production. An admin notice beats a fatal;
  a fatal beats silent corruption.
- Never swallow an exception without handling it. An empty `catch` is a bug with a
  comment-shaped hole where the reason should be.
- Log with enough context to act on: which record, which user, which attempt.
- Anything touching money is idempotent and carries an idempotency key. Assume every
  background job runs twice.
- Multi-row writes go in a transaction. Calls to an external service do not belong
  inside one.

## Performance, in proportion

Correctness first — but these are cheap and the alternative is a rewrite:

- No queries inside loops. Fetch the set, then iterate.
- Anything looping over users or orders processes in batches. An unbounded loop over a
  growing table is a timeout waiting for the site to succeed.
- Cache in the data layer, not the controller, and be explicit about invalidation.
- Do not add an index, a cache layer or a queue on speculation. Measure, then add, and
  say what you measured.
