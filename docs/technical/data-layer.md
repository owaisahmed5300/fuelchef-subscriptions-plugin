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

- `Settings\Settings` - an immutable value object; its constructor validates every field
  (a valid `Cutoff_Unit`, a 0-100 discount, a valid `Subscribe_Applicability`) and throws
  `InvalidArgumentException` on the same "caller's own bug" basis as an entity constructor
  does.
- `Settings\Settings_Store` wraps `get_option()`/`update_option()` under one option key.
  Its `get()` never throws: a missing or no-longer-valid stored value (an old plugin
  version, hand-edited option data) falls back to a default instead of breaking the
  settings screen. `save()` fires `fuelchef_subscriptions/settings/updated`. No custom
  `wp_cache` layer - WordPress's own options cache already covers this.
- A new setting gets a field on `Settings` (with its own validation branch), a default in
  `Settings_Store`, and - if its valid values are a fixed set - a `Values\*` enum-shaped
  class alongside `Cutoff_Unit`/`Subscribe_Applicability`.

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
  underlying resource (the Schedules screen's local blackouts and the Settings screen's
  store-wide ones) share one registered action rather than each registering the same
  `wp_ajax_*` hook, which would run both callbacks on every request.

## Frontend checkout

`plugin/src/Frontend/` is the storefront counterpart to `Admin\` - both checkout
surfaces' delivery date field and subscribe-and-save discount live here, registered by
`Frontend\Provider`. `Frontend\Checkout\Current_Delivery_Window` (schedule + eligible
dates for whatever destination the customer currently has chosen) and `Frontend\Checkout\
Subscribe_And_Save::discount_amount()` (the discount's own business rule) are shared by
classic and block checkout's own field classes rather than duplicated - see "Block
checkout" below for the block-specific pieces (`Frontend\Checkout\Block\*`) that consume
them.

- `WooCommerce\Chosen_Shipping_Destination` turns whatever shipping rate the customer has
  currently chosen into the `(type, key)` pair `Destination_Catalog` and
  `Availability_Service` already understand. A pickup rate (`pickup_location:2`) carries
  its own key; any other rate belongs to whichever zone matches the package's destination
  address, via `WC_Shipping_Zones::get_zone_matching_package()` - the real WooCommerce
  lookup a package's own rates already come from, not a re-derivation from the rate ID.
  `wc_get_chosen_shipping_method_for_package()` (WooCommerce's own helper, already used by
  `wc_cart_totals_shipping_html()`) resolves the default rate the same way WooCommerce
  itself would when nothing has been explicitly picked yet. It calculates shipping itself
  (`WC_Shipping::calculate_shipping()`) whenever `WC_Shipping::get_packages()` is
  currently empty, rather than assuming a caller already did - classic checkout's own page
  render and AJAX handler both happen to calculate it earlier in the same request, but
  that is not true of every context a resolver this general ends up called from (a bare
  custom REST route has nothing upstream to do it at all) - confirmed the hard way, see
  "Block checkout" below.
- `Frontend\Checkout\Delivery_Date_Field` is the controller-shaped piece: it asks
  `Current_Delivery_Window` what schedule and dates apply, and renders, validates and
  persists strictly within what that already decided. Not unit-tested for the same reason
  `Admin\Controllers\*` are not - see "Testing" below.
- **Why it hooks `woocommerce_review_order_after_shipping`, not a custom fragment.**
  WooCommerce's checkout AJAX (`update_order_review`) re-renders the entire order review
  table server-side and returns it as one of its own `fragments` entries
  (`.woocommerce-checkout-review-order-table`), which the browser swaps in wholesale via
  `wc_checkout_params` / `checkout.js`. Hooking an action that already runs inside that
  table means the field refreshes automatically on every shipping/address change, with no
  custom `woocommerce_update_order_review_fragments` filter needed.
- **Why the posted date is captured on `woocommerce_checkout_update_order_review`, not
  read back from the DOM.** The whole table - the field included - is a fresh server
  render on every refresh, so a JavaScript-only selection would be wiped by an unrelated
  change (e.g. the customer editing their address). `Delivery_Date_Field::
  capture_posted_date()` reads the AJAX request's raw `post_data` before rendering, so a
  still-eligible selection survives; `render()` only trusts it once `Availability_Service::
  eligible_dates()` confirms it is still eligible.
- The calendar widget is [flatpickr](https://flatpickr.js.org/), vendored under
  `plugin/assets/lib/flatpickr/` rather than `assets/vendor/` - the latter is caught by
  the root `vendor/` entry in `.gitignore`, meant for Composer's PHP vendor directories,
  and would have silently dropped the library from every commit.
- `Frontend\Checkout\Subscribe_And_Save` reads nothing across requests - whether the
  checkbox is checked is read straight from `$_POST` (`post_data` on an AJAX refresh, the
  field directly on the final submission) every time it is needed, rather than cached in
  `WC()->session`. A session flag would have to be reset somewhere on every full page
  load, and by the time a page-load hook could run, `WC_Cart::calculate_totals()` has
  already run too - a session-based design either shows a stale discount on first paint,
  or leaks a stale one onto the cart page after an abandoned checkout. Reading `$_POST`
  fresh on every use makes a plain page load (no relevant `$_POST` at all) unchecked by
  construction, with nothing to reset.
- Its `discount_amount()` is the one piece of this feature with a real business rule (the
  `Subscribe_Applicability::RENEWAL_ONLY` gate) and is unit-tested; `maybe_apply_discount()`
  around it is the thin, untested glue that reads the request and calls `WC_Cart::add_fee()`.
- **`WC_Cart::get_subtotal()` returns a numeric string, not the `float` its own PHPStan
  stub and docblock claim.** Caught at runtime, not by static analysis - `declare(strict_types=1)`
  on a method with a `float` parameter throws a `TypeError` rather than silently
  coercing, which is exactly what surfaced this. Cast at the one call site rather than
  loosening `discount_amount()`'s signature.
- The discount is a negative `WC_Cart::add_fee()`, the standard WooCommerce mechanism for
  a cart-level discount not tied to a coupon; its own docblock says "do not enter negative
  amounts" but the real `WC_Cart_Fees::add_fee()` implementation never enforces that -
  verified against source rather than trusted at face value.

### Block checkout

`Frontend\Checkout\Block\*` is the Checkout block's own delivery date field and
subscribe-and-save discount, registered via `woocommerce_register_additional_checkout_
field()`. It shares `Current_Delivery_Window` and `Subscribe_And_Save::discount_amount()`
with classic checkout, but the two checkout surfaces need genuinely different integration
code around them - confirmed by reading the installed WooCommerce Blocks source directly,
not assumed from its own docs (`docs/woocommerce-reference/` describes a `date` field
type this installed version does not actually have - only `text`, `select` and
`checkbox` exist in the real `CheckoutFields::$supported_field_types`).

- **The date field is `type: 'text'`, not `date`**, enhanced client-side with flatpickr
  by `assets/checkout/js/block-delivery-date-field.js` - the same widget as classic
  checkout, applied differently since the Checkout block re-renders via React rather than
  a jQuery fragment swap. The script uses a `MutationObserver` on `document.body` to catch
  the field's input appearing (there is no `updated_checkout`-equivalent event to hook),
  and refetches eligible dates from this plugin's own read-only REST route
  (`fuelchef-subscriptions/v1/eligible-dates`) on any `change`/`input` bubbling up the
  page, since the block checkout's own field/form class names are not a stable contract
  worth depending on.
- **Why a custom REST route at all.** A registered field's `attributes` are fixed once, at
  `woocommerce_init` registration time - they cannot carry a live, per-request eligible-
  dates list the way classic checkout's server-rendered fragment does. The route has
  nothing upstream to load the cart or calculate shipping for it (a bare REST request is
  its own, otherwise-empty request), so it calls `wc_load_cart()` itself before asking
  `Current_Delivery_Window` anything - `Chosen_Shipping_Destination` handles the shipping
  calculation part of that itself, per the note above.
- **The discount cannot use `WC_Cart`'s fee pipeline at all**, not even at final
  placement. WooCommerce Blocks defers creating the checkout's draft order until the
  customer actually places it (a 10.8.0 change), and the *only* `calculate_totals()` call
  in the place-order request runs *before* that draft order is created and its `order`-
  location fields are persisted onto it - confirmed by reading the request flow through
  `StoreApi\Routes\V1\Checkout` and `StoreApi\Utilities\CheckoutTrait` directly, and by
  watching a `woocommerce_cart_calculate_fees` callback never fire with a usable value in
  practice. `Block\Subscribe_And_Save::apply_discount()` instead adds the discount as a
  `WC_Order_Item_Fee` directly on the order, hooked to
  `woocommerce_store_api_checkout_update_order_from_request` - not the more obviously-
  named `woocommerce_store_api_checkout_update_order_meta`, which fires *before*
  `persist_additional_fields_for_order()` runs and so cannot read the checkbox's value
  either. Both were tried end to end against the real site before settling on the one that
  actually works; the source comments in `Block\Subscribe_And_Save` document the ordering
  in more detail.
- One practical consequence: block checkout has no live "watch the total change" preview
  before placing the order the way classic checkout's AJAX refresh gives it - only classic
  checkout does. The discount is still always correct on the order that actually gets
  created, which is what the money depends on.

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
