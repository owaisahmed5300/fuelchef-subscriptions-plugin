# Instant-Save Admin Actions and Closures Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every action on the Schedules screen save itself instantly (no "Save
Schedule" button), move global closures off the Settings screen onto their own
"Closures" admin page (unchanged behaviour, new home), and clean up the surrounding UI
(delete-button placement, per-row sidebar delete, simplified confirmation copy).

**Architecture:** Split `Settings_Controller`'s blackout handling into a new
`Closures_Controller` with its own menu entry, template and JS bundle. Replace
`Schedules_Controller`'s batch weekday-save action with a singular one
(`Schedule_Service::update_weekday()` already exists and already has full test
coverage — only the batch wrapper `update_weekdays()` is removed). Rewrite
`schedules.js` so every control fires its own AJAX call immediately, optimistic-update
first and revert-on-error, mirroring the pattern the blackout calendar already uses.

**Tech Stack:** PHP 8.0+ (WordPress plugin), vanilla JS + jQuery (`FCS.post`), PHPUnit +
Brain Monkey/Mockery, PHPCS (WordPress-Extra via `phpcs.xml.dist`), PHPStan level 10.

**Spec:** `docs/superpowers/specs/2026-09-19-instant-admin-saves-design.md`

## Global Constraints

- Import prefixed namespaces only (`FuelChef\Subscriptions\Dependencies\...`) — not
  relevant to this plan (no new Composer dependency), noted per repo convention.
- Controllers are view/glue code and are **never unit-tested** (see the 2026-09-13
  admin-scheduling-layer-design spec's "Testing" section) — do not add
  `Schedules_ControllerTest`, `Settings_ControllerTest`, or `Closures_ControllerTest`.
- Every PHPDoc needs a description; `@param`/`@return`/`@throws` need a period-ended
  explanation; never `@throws InvalidArgumentException`.
- Every JS/PHP string shown to an admin goes through `Admin\Assets::strings()`
  (i18n) — never hardcode English text in `schedules.js`/`closures.js`.
- Keep PHPCS and PHPStan level 10 clean throughout — run both before the final commit
  of each task.
- "Global" is never used as a UI label on its own (see repo convention) — the page is
  titled "Closures", matching the existing tab label.

---

### Task 1: Extract `Closures_Controller` from `Settings_Controller`

**Files:**
- Create: `plugin/src/Admin/Controllers/Closures_Controller.php`
- Modify: `plugin/src/Admin/Controllers/Settings_Controller.php`

**Interfaces:**
- Produces: `Closures_Controller` with `register(): void`, `render(): void`,
  `ajax_save_blackout(): void`, `ajax_delete_blackout(): void` — identical bodies to
  the methods being removed from `Settings_Controller`, registering
  `wp_ajax_fcs_save_blackout` and `wp_ajax_fcs_delete_blackout`.
- Consumes: `Blackout_Service`, `Blackout_Repository`, `Renderer` (already exist),
  `Presents_Blackouts`/`Verifies_Ajax_Request` traits (already exist).

- [ ] **Step 1: Create `Closures_Controller`**

```php
<?php
/**
 * Closures controller.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Controllers;

use FuelChef\Subscriptions\Admin\Concerns\Presents_Blackouts;
use FuelChef\Subscriptions\Admin\Concerns\Verifies_Ajax_Request;
use FuelChef\Subscriptions\Admin\Menu;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Services\Scheduling\Blackout_Service;
use FuelChef\Subscriptions\Utils\Narrow;
use FuelChef\Subscriptions\Utils\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Closures screen and handles its ajax actions.
 *
 * `fcs_save_blackout`/`fcs_delete_blackout` are registered here only, even though
 * `Schedules_Controller`'s screen uses them too for a schedule-scoped blackout - both
 * delegate to `Blackout_Service`, which already accepts a nullable schedule ID, so
 * one shared handler covers both cases and avoids two callbacks racing on the same
 * action.
 */
final class Closures_Controller {


	use Presents_Blackouts;
	use Verifies_Ajax_Request;

	/**
	 * Creates the controller.
	 */
	public function __construct(
		private Blackout_Service $blackout_service,
		private Blackout_Repository $blackout_repository,
		private Renderer $renderer
	) {
	}

	/**
	 * Registers this controller's ajax actions.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fcs_save_blackout', [ $this, 'ajax_save_blackout' ] );
		add_action( 'wp_ajax_fcs_delete_blackout', [ $this, 'ajax_delete_blackout' ] );
	}

	/**
	 * Renders the Closures screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'fuelchef-subscriptions' ) );
		}

		wp_localize_script(
			'fcs-admin-closures',
			'fcsClosures',
			[ 'blackouts' => $this->blackouts_for_js( $this->blackout_repository->find_by_schedule( null ) ) ]
		);

		$html = $this->renderer->render( 'admin/closures', [] );

		// The template escapes every dynamic value itself; this is its own fully-built page markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Creates or updates a store-wide blackout date.
	 *
	 * Shared with `Schedules_Controller` - see `register()`.
	 */
	public function ajax_save_blackout(): void {
		$this->verify_ajax_request();

		$blackout_id     = absint( Narrow::string( $_POST['blackout_id'] ?? null ) );
		$raw_schedule_id = absint( Narrow::string( $_POST['schedule_id'] ?? null ) );
		$schedule_id     = $raw_schedule_id > 0 ? $raw_schedule_id : null;
		$date            = sanitize_text_field( wp_unslash( Narrow::string( $_POST['date'] ?? null ) ) );
		$reason          = isset( $_POST['reason'] )
			? sanitize_textarea_field( wp_unslash( Narrow::string( $_POST['reason'] ) ) )
			: null;

		try {
			$blackout = $blackout_id > 0
				? $this->blackout_service->update_reason( $blackout_id, '' === $reason ? null : $reason )
				: $this->blackout_service->add( $schedule_id, $date, '' === $reason ? null : $reason );
		} catch ( Validation_Exception $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		wp_send_json_success(
			[
				'id'     => $blackout->id(),
				'date'   => $blackout->date(),
				'reason' => $blackout->reason(),
			]
		);
	}

	/**
	 * Removes a blackout date.
	 *
	 * Shared with `Schedules_Controller` - see `register()`.
	 */
	public function ajax_delete_blackout(): void {
		$this->verify_ajax_request();

		$this->blackout_service->remove( absint( Narrow::string( $_POST['blackout_id'] ?? null ) ) );

		wp_send_json_success();
	}
}
```

- [ ] **Step 2: Trim `Settings_Controller`**

Remove: the `Presents_Blackouts` trait usage, the `Blackout_Service`/
`Blackout_Repository` constructor parameters, the `register()` doc comment about
sharing (delete it — `Closures_Controller` now owns that note), the
`add_action( 'wp_ajax_fcs_save_blackout', ... )` and
`add_action( 'wp_ajax_fcs_delete_blackout', ... )` lines, the `blackouts` key in
`render()`'s `wp_localize_script()` call, and both `ajax_save_blackout()` and
`ajax_delete_blackout()` methods in full. The file becomes:

```php
<?php
/**
 * Settings controller.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Controllers;

use FuelChef\Subscriptions\Admin\Concerns\Reads_Request_Fields;
use FuelChef\Subscriptions\Admin\Concerns\Verifies_Ajax_Request;
use FuelChef\Subscriptions\Admin\Menu;
use FuelChef\Subscriptions\Services\Settings_Service;
use FuelChef\Subscriptions\Utils\Renderer;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Settings screen and handles its ajax actions.
 */
final class Settings_Controller {


	use Reads_Request_Fields;
	use Verifies_Ajax_Request;

	/**
	 * Creates the controller.
	 */
	public function __construct(
		private Settings_Service $settings_service,
		private Renderer $renderer
	) {
	}

	/**
	 * Registers this controller's ajax actions.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fcs_save_settings', [ $this, 'ajax_save_settings' ] );
	}

	/**
	 * Renders the Settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'fuelchef-subscriptions' ) );
		}

		$html = $this->renderer->render(
			'admin/settings',
			[
				'settings'        => $this->settings_service->get(),
				'applicabilities' => Subscribe_Applicability::all(),
				'currency_symbol' => get_woocommerce_currency_symbol(),
			]
		);

		// The template escapes every dynamic value itself; this is its own fully-built page markup.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Saves the cutoff, discount, applicability, eligibility and checkout copy settings.
	 */
	public function ajax_save_settings(): void {
		$this->verify_ajax_request();

		try {
			$settings = new Settings(
				cutoff_days: $this->posted_int( 'cutoff_days' ),
				cutoff_time: $this->posted_text( 'cutoff_time' ),
				subscribe_discount_percent: $this->posted_int( 'subscribe_discount_percent' ),
				subscribe_applicability: $this->posted_text( 'subscribe_applicability' ),
				max_fulfilment_window_days: $this->posted_int( 'max_fulfilment_window_days' ),
				fulfilment_date_label: $this->posted_text( 'fulfilment_date_label' ),
				fulfilment_date_description: $this->posted_text( 'fulfilment_date_description' ),
				subscribe_save_label: $this->posted_text( 'subscribe_save_label' ),
				subscribe_save_description: $this->posted_text( 'subscribe_save_description' ),
				minimum_order_amount: $this->posted_float( 'minimum_order_amount' ),
				minimum_cart_quantity: $this->posted_int( 'minimum_cart_quantity' ),
				ineligible_message: $this->posted_text( 'ineligible_message' ),
				logged_out_message: $this->posted_text( 'logged_out_message' ),
				fulfilment_window_message: $this->posted_text( 'fulfilment_window_message' )
			);
		} catch ( InvalidArgumentException $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		$this->settings_service->save( $settings );

		wp_send_json_success();
	}
}
```

- [ ] **Step 3: Verify**

Run: `./scripts/dev phpstan` and `./scripts/dev phpcs`.
Expected: no errors from either file.

- [ ] **Step 4: Commit**

```bash
git add plugin/src/Admin/Controllers/Closures_Controller.php plugin/src/Admin/Controllers/Settings_Controller.php
git commit -m "refactor(admin): extract Closures_Controller from Settings_Controller"
```

---

### Task 2: Wire the Closures screen into the menu, DI container and asset loader

**Files:**
- Modify: `plugin/src/Admin/Menu.php`
- Modify: `plugin/src/Admin/Provider.php`
- Modify: `plugin/src/Admin/Assets.php`

**Interfaces:**
- Consumes: `Closures_Controller` from Task 1.
- Produces: `Menu::CLOSURES_SLUG = 'fuelchef-closures'`; `Admin\Assets` enqueues
  `fcs-admin-closures` style/script on that screen.

- [ ] **Step 1: Add the submenu to `Menu`**

In `plugin/src/Admin/Menu.php`, add the constant, the constructor parameter, and the
`add_submenu_page()` call:

```php
	/**
	 * Slug of the Closures screen.
	 */
	public const CLOSURES_SLUG = 'fuelchef-closures';
```

```php
	public function __construct(
		private Settings_Controller $settings_controller,
		private Schedules_Controller $schedules_controller,
		private Closures_Controller $closures_controller
	) {
	}
```

After the existing "Schedules" `add_submenu_page()` call, in `register()`:

```php
		add_submenu_page(
			self::SETTINGS_SLUG,
			esc_html__( 'Closures', 'fuelchef-subscriptions' ),
			esc_html__( 'Closures', 'fuelchef-subscriptions' ),
			self::CAPABILITY,
			self::CLOSURES_SLUG,
			[ $this->closures_controller, 'render' ]
		);
```

Add `use FuelChef\Subscriptions\Admin\Controllers\Closures_Controller;` to the
existing `use` block (alphabetical, before `Schedules_Controller`).

- [ ] **Step 2: Register the controller and update `Menu`'s parameters in `Provider`**

In `plugin/src/Admin/Provider.php`, replace the `Settings_Controller` singleton's
parameters (drop the two blackout ones), add a `Closures_Controller` singleton, and
add it to `Menu`'s parameters:

```php
		$container
			->singleton( Settings_Controller::class )
			->addParameter( Settings_Service::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Closures_Controller::class )
			->addParameter( Blackout_Service::class, true )
			->addParameter( Blackout_Repository::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Schedules_Controller::class )
			->addParameter( Schedule_Service::class, true )
			->addParameter( Schedule_Repository::class, true )
			->addParameter( Schedule_Weekday_Repository::class, true )
			->addParameter( Blackout_Repository::class, true )
			->addParameter( Schedule_Destination_Repository::class, true )
			->addParameter( Destination_Catalog_Service::class, true )
			->addParameter( Renderer::class, true );

		$container
			->singleton( Menu::class )
			->addParameter( Settings_Controller::class, true )
			->addParameter( Schedules_Controller::class, true )
			->addParameter( Closures_Controller::class, true );
```

Add `use FuelChef\Subscriptions\Admin\Controllers\Closures_Controller;` to the
`use` block. In `boot()`, add:

```php
		$container->get( Closures_Controller::class )->register();
```

right after the `Settings_Controller::class` line.

- [ ] **Step 3: Enqueue `closures` assets in `Assets::enqueue()`**

In `plugin/src/Admin/Assets.php`, add the new branch to the `match`:

```php
		$screen = match ( $page ) {
			Menu::SETTINGS_SLUG => 'settings',
			Menu::SCHEDULES_SLUG => 'schedules',
			Menu::CLOSURES_SLUG => 'closures',
			default => null,
		};
```

- [ ] **Step 4: Verify**

Run `./scripts/dev up` if not already running, log into `/wp-admin`, and confirm a
"Closures" submenu now appears under "FuelChef" (it will 404-style blank/error until
Task 3 adds the template and JS — that's expected at this point; just confirm the
menu entry itself renders and the URL is `admin.php?page=fuelchef-closures`). Run
`./scripts/dev phpstan` and `./scripts/dev phpcs`; expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add plugin/src/Admin/Menu.php plugin/src/Admin/Provider.php plugin/src/Admin/Assets.php
git commit -m "feat(admin): register the Closures screen's menu entry and assets"
```

---

### Task 3: New Closures template, JS and CSS (extracted from Settings)

**Files:**
- Create: `plugin/templates/admin/closures.php`
- Create: `plugin/assets/admin/js/closures.js`
- Create: `plugin/assets/admin/css/closures.css`
- Modify: `plugin/templates/admin/settings.php`
- Modify: `plugin/assets/admin/js/settings.js`
- Modify: `plugin/assets/admin/css/settings.css`

**Interfaces:**
- Consumes: `fcsClosures.blackouts` (localized by `Closures_Controller::render()` in
  Task 1), `FCS.createCalendar()` (unchanged, from `common.js`).

- [ ] **Step 1: Create `plugin/templates/admin/closures.php`**

```php
<?php
/**
 * Closures admin screen.
 *
 * Rendered by Closures_Controller::render(). The blackout calendar reads its own data
 * from the `fcsClosures` script localization instead - see the controller.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap">
	<div class="fcs-admin fcs-wrap fcs-editor-shell">
		<header class="fcs-page-header fcs-editor-header">
			<h1 class="fcs-page-title">
				<?php esc_html_e( 'FuelChef Subscriptions', 'fuelchef-subscriptions' ); ?>
				&rsaquo;
				<?php esc_html_e( 'Closures', 'fuelchef-subscriptions' ); ?>
			</h1>
			<p class="fcs-page-subtitle">
				<?php esc_html_e( 'Block dates that close recurring orders for the entire store.', 'fuelchef-subscriptions' ); ?>
			</p>
		</header>

		<div class="fcs-card">
			<div class="fcs-card__header">
				<h2 class="fcs-card__title">
					<?php esc_html_e( 'Global Store Closures', 'fuelchef-subscriptions' ); ?>
				</h2>
			</div>
			<div class="fcs-card__body">
				<p class="fcs-card__intro">
					<?php
					esc_html_e(
						'Dates specified here close recurring orders for the entire store regardless of location. Use this for statutory holidays or full warehouse shutdowns.',
						'fuelchef-subscriptions'
					);
					?>
				</p>

				<div class="fcs-calendar-toolbar">
					<button
						type="button"
						class="fcs-btn fcs-btn--icon"
						id="calPrevMonth"
						aria-label="<?php esc_attr_e( 'Previous month', 'fuelchef-subscriptions' ); ?>"
						title="<?php esc_attr_e( 'Previous month', 'fuelchef-subscriptions' ); ?>"
					>
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
							<path d="M11 4.5 6.5 9l4.5 4.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
					<strong class="fcs-calendar-toolbar__title" id="globalCalendarMonth"></strong>
					<button
						type="button"
						class="fcs-btn fcs-btn--icon"
						id="calNextMonth"
						aria-label="<?php esc_attr_e( 'Next month', 'fuelchef-subscriptions' ); ?>"
						title="<?php esc_attr_e( 'Next month', 'fuelchef-subscriptions' ); ?>"
					>
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
							<path d="M7 4.5 11.5 9 7 13.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
				</div>

				<div class="fcs-calendar" id="globalCalendar"></div>

				<div class="fcs-calendar-legend">
					<span class="fcs-calendar-legend__dot"></span>
					<?php esc_html_e( 'Closed store-wide', 'fuelchef-subscriptions' ); ?>
					<span>
						<?php
						esc_html_e(
							'(Click any date to close. Click an existing closed date to edit note.)',
							'fuelchef-subscriptions'
						);
						?>
					</span>
				</div>

				<div class="fcs-summary-list" id="globalUnavailableList"></div>
			</div>
		</div>

		<div class="fcs-popover" id="datePopover" popover="auto" role="dialog" aria-labelledby="datePopoverTitle">
			<div class="fcs-popover__arrow" data-popper-arrow></div>
			<div class="fcs-popover__header">
				<div>
					<div class="fcs-popover__title" id="datePopoverTitle" data-pop-title></div>
					<div class="fcs-popover__subtitle">
						<?php esc_html_e( 'Global Store Closure', 'fuelchef-subscriptions' ); ?>
					</div>
				</div>
				<button
					type="button"
					class="fcs-popover__close"
					data-pop-close
					aria-label="<?php esc_attr_e( 'Close', 'fuelchef-subscriptions' ); ?>"
				>
					<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
						<path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
					</svg>
				</button>
			</div>
			<label class="fcs-popover__label">
				<?php esc_html_e( 'Closure note (optional)', 'fuelchef-subscriptions' ); ?>
			</label>
			<textarea
				class="fcs-textarea"
				data-pop-reason
				maxlength="255"
				placeholder="<?php esc_attr_e( 'e.g. National Holiday', 'fuelchef-subscriptions' ); ?>"
			></textarea>
			<div class="fcs-popover__count"><span data-pop-count>0</span> / 255</div>
			<div class="fcs-popover__actions">
				<button type="button" class="fcs-btn fcs-btn--danger" data-pop-remove>
					<?php esc_html_e( 'Remove date', 'fuelchef-subscriptions' ); ?>
				</button>
				<button type="button" class="fcs-btn fcs-btn--primary" data-pop-save>
					<?php esc_html_e( 'Save note', 'fuelchef-subscriptions' ); ?>
				</button>
			</div>
		</div>

		<div class="fcs-toast" id="fcsToast" role="status" aria-live="polite">
			<span class="fcs-toast__icon" aria-hidden="true"></span>
			<span class="fcs-toast__message" data-toast-message></span>
		</div>
	</div>
</div>
```

- [ ] **Step 2: Create `plugin/assets/admin/js/closures.js`**

```js
/**
 * FuelChef Subscriptions - Closures Page Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  function saveMessage(response, fallback) {
    return (response.data && response.data.message) ? response.data.message : fallback;
  }

  FCS.createCalendar({
    calendarEl: document.getElementById('globalCalendar'),
    monthLabelEl: document.getElementById('globalCalendarMonth'),
    summaryEl: document.getElementById('globalUnavailableList'),
    prevBtn: document.getElementById('calPrevMonth'),
    nextBtn: document.getElementById('calNextMonth'),
    popoverEl: document.getElementById('datePopover'),
    blackouts: window.fcsClosures.blackouts,
    emptyMessage: window.fcsAdmin.i18n.noGlobalClosures,
    onCreate: (date) => FCS.post('fcs_save_blackout', { date }).then((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotAddDate), 'error');
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onSave: (id, reason) => FCS.post('fcs_save_blackout', { blackout_id: id, reason }).then((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveNote), 'error');
        return null;
      }
      return { id: response.data.id, date: response.data.date, reason: response.data.reason };
    }),
    onRemove: (id) => FCS.post('fcs_delete_blackout', { blackout_id: id }).then((response) => response.success)
  });
});
```

This is the "Global Blackouts Calendar" block moved verbatim out of `settings.js`
(only the `blackouts` source changes, from `window.fcsSettings.blackouts` to
`window.fcsClosures.blackouts`). It does not call `FCS.State` — the blackout
calendar never did, on either screen.

- [ ] **Step 3: Create `plugin/assets/admin/css/closures.css`**

```css
/**
 * FuelChef Subscriptions — Closures composition
 * WordPress / Gutenberg design-system treatment.
 */

.fcs-admin .fcs-card { max-width: 760px; }
```

- [ ] **Step 4: Remove the Global Closures tab from `plugin/templates/admin/settings.php`**

Delete the `#tab-blackouts-trigger` nav-tab button, the entire
`<section ... id="tab-blackouts">...</section>` block, and the `datePopover` markup
(it moved to `closures.php` — the schedules screen keeps its own, separate,
already-existing popover for local closures, so nothing else references this one).
The remaining nav tabs are `Order Cutoff` and `Checkout Fields`; make
`#tab-cutoff-trigger` the one with `fcs-nav-tab--active`/`aria-selected="true"` and
`#tab-cutoff` the one with `fcs-tab-panel--active`, since blackouts (previously
first/default) no longer exists.

- [ ] **Step 5: Remove the Global Blackouts Calendar block from `plugin/assets/admin/js/settings.js`**

Delete the `FCS.createCalendar({...})` call for `globalCalendar` (lines currently
between the `saveSettingsBtn` block and the "Track inputs for changes" block). The
file keeps `FCS.State.init()`, the `saveSettingsBtn` handler, and the
`document.querySelectorAll('input, select, textarea')` dirty-tracker for the
remaining fields — removing the calendar's popover `textarea` from this screen also
fixes a latent quirk where editing a blackout's reason note used to mark the
Settings screen dirty even though the blackout itself saved immediately.

- [ ] **Step 6: Remove the now-dead `.fcs-calendar` rule from `plugin/assets/admin/css/settings.css`**

Delete `.fcs-admin #settingsForm .fcs-calendar { max-width: 760px; }` — there is no
longer a calendar inside `#settingsForm`.

- [ ] **Step 7: Verify**

Visit `/wp-admin/admin.php?page=fuelchef-closures` and confirm: the calendar renders
with any existing global blackout dates, clicking an empty date opens the popover and
creating a closure works, clicking an existing date lets you edit its note and remove
it, and every action shows a toast — all with no page reload and no Save button
anywhere on the page. Then visit `/wp-admin/admin.php?page=fuelchef-settings` and
confirm it now opens on "Order Cutoff" with only two tabs, and that "Save Changes"
still works for those fields. Run `./scripts/dev phpcs` on all six touched/created files.

- [ ] **Step 8: Commit**

```bash
git add plugin/templates/admin/closures.php plugin/assets/admin/js/closures.js plugin/assets/admin/css/closures.css plugin/templates/admin/settings.php plugin/assets/admin/js/settings.js plugin/assets/admin/css/settings.css
git commit -m "feat(admin): move global closures to their own Closures page"
```

---

### Task 4: Singular weekday-save endpoint

**Files:**
- Modify: `plugin/src/Services/Scheduling/Schedule_Service.php`
- Modify: `plugin/src/Admin/Controllers/Schedules_Controller.php`
- Modify: `plugin/src/Admin/Concerns/Reads_Request_Fields.php`
- Modify: `tests/Unit/Services/Scheduling/Schedule_Service_Test.php`

**Interfaces:**
- Consumes: `Schedule_Service::update_weekday( int $schedule_id, int $day_of_week, bool $enabled, string $start_time, string $end_time ): Schedule_Weekday` — already exists, unchanged, already fully tested (`test_update_weekday_*` in the test file).
- Produces: new ajax action `fcs_save_schedule_weekday`, new
  `Reads_Request_Fields::posted_bool( string $key ): bool`.
- Removes: `Schedule_Service::update_weekdays()` (plural), ajax action
  `fcs_save_schedule_weekdays`, its two tests.

- [ ] **Step 1: Remove the two now-obsolete tests**

In `tests/Unit/Services/Scheduling/Schedule_Service_Test.php`, delete
`test_update_weekdays_saves_every_row_in_one_transaction()` and
`test_update_weekdays_rolls_back_and_saves_nothing_when_a_row_is_invalid()` in full
(lines 188–253 as currently numbered — everything between
`test_update_weekday_rejects_a_day_the_schedule_has_no_row_for()`'s closing brace and
the `weekday_row()` helper's docblock). Keep the `weekday_row()` helper itself — it's
still used by the `copy_weekday_to_days_below` tests.

- [ ] **Step 2: Run the suite to confirm it's still green**

Run: `./scripts/dev test`
Expected: 240 tests passing (242 minus the 2 just removed), no failures.

- [ ] **Step 3: Remove `update_weekdays()` from `Schedule_Service`**

Delete this method from `plugin/src/Services/Scheduling/Schedule_Service.php` (the
"Updates every weekday row passed in..." one, immediately after `update_weekday()`):

```php
	public function update_weekdays( int $schedule_id, array $rows ): array {
		return $this->transactions->run(
			function () use ( $schedule_id, $rows ): array {
				return array_map(
					fn ( array $row ): Schedule_Weekday => $this->update_weekday(
						$schedule_id,
						$row['day_of_week'],
						$row['enabled'],
						$row['start_time'],
						$row['end_time']
					),
					$rows
				);
			}
		);
	}
```

`Transaction_Manager` is still used elsewhere in this file (`assign_destinations()`),
so its constructor parameter and `use` import stay.

- [ ] **Step 4: Add `posted_bool()` to `Reads_Request_Fields`**

```php
	/**
	 * A posted checkbox field, true only when it was submitted as '1'.
	 */
	private function posted_bool( string $key ): bool {
		return '1' === sanitize_text_field( wp_unslash( Narrow::string( $_POST[ $key ] ?? null ) ) );
	}
```

- [ ] **Step 5: Replace `ajax_save_schedule_weekdays()` with `ajax_save_schedule_weekday()` in `Schedules_Controller`**

Replace the whole method (and its `register()` line
`add_action( 'wp_ajax_fcs_save_schedule_weekdays', ... )`) with:

```php
		add_action( 'wp_ajax_fcs_save_schedule_weekday', [ $this, 'ajax_save_schedule_weekday' ] );
```

```php
	/**
	 * Updates one weekday's availability, start time and end time - the screen calls
	 * this the moment the admin toggles a day or changes a time, not behind any
	 * separate save action.
	 */
	public function ajax_save_schedule_weekday(): void {
		$this->verify_ajax_request();

		try {
			$weekday = $this->schedule_service->update_weekday(
				$this->posted_int( 'schedule_id' ),
				$this->posted_int( 'day_of_week' ),
				$this->posted_bool( 'enabled' ),
				$this->posted_text( 'start_time' ),
				$this->posted_text( 'end_time' )
			);
		} catch ( Validation_Exception $exception ) {
			wp_send_json_error( [ 'message' => $exception->getMessage() ] );
		}

		wp_send_json_success( [ 'weekday' => $this->weekday_for_js( $weekday ) ] );
	}
```

- [ ] **Step 6: Verify**

Run: `./scripts/dev test` (expect 240 passing), then `./scripts/dev phpstan` and
`./scripts/dev phpcs`.

- [ ] **Step 7: Commit**

```bash
git add plugin/src/Services/Scheduling/Schedule_Service.php plugin/src/Admin/Controllers/Schedules_Controller.php plugin/src/Admin/Concerns/Reads_Request_Fields.php tests/Unit/Services/Scheduling/Schedule_Service_Test.php
git commit -m "feat(schedules): replace the batch weekday-save action with a singular one"
```

---

### Task 5: Schedules template restructure

**Files:**
- Modify: `plugin/templates/admin/schedules.php`
- Modify: `plugin/assets/admin/css/schedules.css`

**Interfaces:**
- Produces: `.fcs-schedule-nav__item` (wraps each sidebar row), `.fcs-schedule-nav__more`
  (the "⋮" trigger), `.fcs-schedule-nav__menu` (the dropdown), each with a
  `data-schedule-id` attribute the Task 6 JS reads.

- [ ] **Step 1: Move the Delete Schedule button into the title row**

Replace:

```php
					<div class="fcs-title-wrap">
						<input
							type="text"
							class="fcs-input fcs-title-input"
							id="scheduleTitle"
							value="<?php echo esc_attr( $selected->name() ); ?>"
							placeholder="<?php echo esc_attr( $schedule_name_placeholder ); ?>"
							aria-label="<?php echo esc_attr( $schedule_name_placeholder ); ?>"
						>
					</div>
```

with:

```php
					<div class="fcs-title-wrap">
						<input
							type="text"
							class="fcs-input fcs-title-input"
							id="scheduleTitle"
							value="<?php echo esc_attr( $selected->name() ); ?>"
							placeholder="<?php echo esc_attr( $schedule_name_placeholder ); ?>"
							aria-label="<?php echo esc_attr( $schedule_name_placeholder ); ?>"
						>
						<button type="button" class="fcs-btn fcs-btn--danger" id="deleteScheduleBtn">
							<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
								<path d="M2.5 3.5h9M5.5 3.5v-1a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1m-5.5 0 .5 8.2a1 1 0 0 0 1 .8h4a1 1 0 0 0 1-.8l.5-8.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<?php esc_html_e( 'Delete Schedule', 'fuelchef-subscriptions' ); ?>
						</button>
					</div>
```

- [ ] **Step 2: Remove the bottom submit bar**

Delete the entire block:

```php
					<div class="fcs-submit-bar">
						<div class="fcs-submit-bar__actions">
							<button type="button" class="fcs-btn fcs-btn--primary" id="saveScheduleBtn">
								<?php esc_html_e( 'Save Schedule', 'fuelchef-subscriptions' ); ?>
							</button>
							<button type="button" class="fcs-btn fcs-btn--danger" id="deleteScheduleBtn">
								<?php esc_html_e( 'Delete Schedule', 'fuelchef-subscriptions' ); ?>
							</button>
						</div>
						<span class="fcs-save-status fcs-save-status--saved" id="fcsSaveStatus">
					&#10003;
					<?php esc_html_e( 'All changes saved', 'fuelchef-subscriptions' ); ?>
				</span>
					</div>
```

(the old `deleteScheduleBtn` here is deleted along with the rest of this block — it
now lives only in the title row from Step 1).

- [ ] **Step 3: Add the sidebar per-row delete menu**

Replace the `<li>...</li>` loop body with:

```php
								<li class="fcs-schedule-nav__item">
									<a
										class="fcs-schedule-nav__link<?php echo $is_active ? ' fcs-schedule-nav__link--active' : ''; ?>"
										href="<?php echo esc_url( $base_url . '&schedule_id=' . $schedule->id() ); ?>"
										<?php echo $is_active ? 'aria-current="true"' : ''; ?>
									>
										<div>
											<div class="fcs-schedule-nav__title">
												<?php echo esc_html( $schedule->name() ); ?>
											</div>
											<div class="fcs-schedule-nav__meta">
												<?php
												echo esc_html(
													sprintf(
													/* translators: %d: number of destinations assigned to the schedule. */
														_n(
															'%d destination',
															'%d destinations',
															$destination_count,
															'fuelchef-subscriptions'
														),
														$destination_count
													)
												);
												?>
											</div>
										</div>
										<svg class="fcs-schedule-nav__chev" width="16" height="16" viewBox="0 0 16 16"
											fill="none" aria-hidden="true">
											<path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.6"
													stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</a>
									<div class="fcs-schedule-nav__menu-wrap">
										<button
											type="button"
											class="fcs-schedule-nav__more"
											aria-haspopup="true"
											aria-expanded="false"
											aria-label="<?php echo esc_attr(
												sprintf(
												/* translators: %s: schedule name. */
													__( 'More actions for %s', 'fuelchef-subscriptions' ),
													$schedule->name()
												)
											); ?>"
										>
											<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
												<circle cx="8" cy="3.2" r="1.3" fill="currentColor"/>
												<circle cx="8" cy="8" r="1.3" fill="currentColor"/>
												<circle cx="8" cy="12.8" r="1.3" fill="currentColor"/>
											</svg>
										</button>
										<div class="fcs-schedule-nav__menu" role="menu">
											<button
												type="button"
												class="fcs-schedule-nav__menu-item fcs-schedule-nav__menu-item--danger"
												role="menuitem"
												data-action="delete-schedule"
												data-schedule-id="<?php echo esc_attr( (string) $schedule->id() ); ?>"
											>
												<?php esc_html_e( 'Delete', 'fuelchef-subscriptions' ); ?>
											</button>
										</div>
									</div>
								</li>
```

- [ ] **Step 4: Simplify the delete-confirmation copy**

Replace:

```php
					<p class="fcs-modal__body">
						<?php esc_html_e( 'Its weekdays, local closure dates and destination assignments are removed with it. This cannot be undone.', 'fuelchef-subscriptions' ); ?>
					</p>
```

with:

```php
					<p class="fcs-modal__body">
						<?php esc_html_e( 'This cannot be undone.', 'fuelchef-subscriptions' ); ?>
					</p>
```

- [ ] **Step 5: Add the CSS for the title row and sidebar menu**

Append to `plugin/assets/admin/css/schedules.css`:

```css
.fcs-admin .fcs-title-wrap { display: flex; align-items: center; gap: 12px; margin: 0 0 20px; }
.fcs-admin .fcs-title-wrap .fcs-title-input { flex: 1 1 auto; }

.fcs-admin .fcs-schedule-nav__item { position: relative; display: flex; align-items: stretch; }
.fcs-admin .fcs-schedule-nav__item .fcs-schedule-nav__link { flex: 1 1 auto; min-width: 0; }
.fcs-admin .fcs-schedule-nav__menu-wrap { position: relative; flex: 0 0 auto; display: flex; align-items: center; }
.fcs-admin .fcs-schedule-nav__more {
  opacity: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  margin-left: -32px;
  border: 1px solid transparent;
  border-radius: var(--fcs-control-radius);
  background: transparent;
  color: var(--fcs-color-text-tertiary);
  cursor: pointer;
}
.fcs-admin .fcs-schedule-nav__item:hover .fcs-schedule-nav__more,
.fcs-admin .fcs-schedule-nav__item:focus-within .fcs-schedule-nav__more,
.fcs-admin .fcs-schedule-nav__more[aria-expanded="true"] { opacity: 1; }
.fcs-admin .fcs-schedule-nav__more:hover { background: #f6f7f7; color: var(--fcs-color-text); }
.fcs-admin .fcs-schedule-nav__more:focus-visible { outline: none; box-shadow: var(--fcs-focus); }
.fcs-admin .fcs-schedule-nav__menu {
  display: none;
  position: absolute;
  top: 100%;
  right: 8px;
  z-index: 10;
  min-width: 140px;
  margin-top: 4px;
  padding: 4px;
  border: 1px solid var(--fcs-color-border-subtle);
  border-radius: var(--fcs-control-radius);
  background: #fff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
}
.fcs-admin .fcs-schedule-nav__menu--show { display: block; }
.fcs-admin .fcs-schedule-nav__menu-item {
  display: block;
  width: 100%;
  padding: 8px 10px;
  border: 0;
  border-radius: calc(var(--fcs-control-radius) - 2px);
  background: transparent;
  color: var(--fcs-color-text);
  font-size: 13px;
  text-align: left;
  cursor: pointer;
}
.fcs-admin .fcs-schedule-nav__menu-item:hover { background: #f6f7f7; }
.fcs-admin .fcs-schedule-nav__menu-item--danger { color: var(--fcs-color-danger); }
```

(the negative `margin-left` on `.fcs-schedule-nav__more` pulls it visually next to
the chevron rather than adding extra row width — the row's overall height/padding is
unchanged.)

- [ ] **Step 6: Verify**

Visit the Schedules screen. Confirm: Delete Schedule button appears next to the
title, no button or status pill remains at the bottom, hovering (and tab-focusing) a
sidebar row reveals a "⋮" button next to its chevron, clicking it opens a dropdown
with a red "Delete" item, and clicking elsewhere closes the dropdown. (The dropdown's
click handler and the confirm-modal retargeting are wired in Task 6 — at this point
just confirm the markup/CSS shows and hides correctly; clicking "Delete" won't do
anything yet.) Run `./scripts/dev phpcs` on both files.

- [ ] **Step 7: Commit**

```bash
git add plugin/templates/admin/schedules.php plugin/assets/admin/css/schedules.css
git commit -m "feat(schedules): move delete to the title row, add a per-row sidebar delete menu"
```

---

### Task 6: `schedules.js` instant-save rewrite

**Files:**
- Modify: `plugin/assets/admin/js/schedules.js`
- Modify: `plugin/src/Admin/Assets.php` (new/removed i18n strings only)

**Interfaces:**
- Consumes: `fcs_save_schedule_weekday` (Task 4), `.fcs-schedule-nav__more` /
  `.fcs-schedule-nav__menu` / `[data-action="delete-schedule"]` (Task 5).
- Produces: none consumed by later tasks — this is the last screen-behaviour task.

- [ ] **Step 1: Update `Admin\Assets::strings()`**

Add these entries (alphabetised into the existing array by matching neighbours where
sensible — exact position doesn't matter, PHPCS doesn't enforce array-key order
here):

```php
			'weekdaySaved'               => __( 'Weekday updated', 'fuelchef-subscriptions' ),
			'destinationAdded'           => __( 'Destination added', 'fuelchef-subscriptions' ),
			'destinationRemoved'         => __( 'Destination removed', 'fuelchef-subscriptions' ),
			'scheduleDeleted'            => __( 'Schedule deleted', 'fuelchef-subscriptions' ),
```

Remove `'scheduleSaved' => __( 'Schedule saved successfully', 'fuelchef-subscriptions' ),`
— it was only ever shown by the now-removed "Save Schedule" button.

- [ ] **Step 2: Remove the dirty-tracking and batch-save machinery**

In `plugin/assets/admin/js/schedules.js`, remove: `FCS.State.init()` (line 6),
`weekdaysPayload()`, the entire `saveScheduleBtn` click handler block (the
`jQuery.when(...)` one), and every `FCS.State.markDirty()` call. Keep
`destinationsPayload()` — it's reused in Step 4.

- [ ] **Step 3: Make weekday toggle/time changes save instantly**

Replace the checkbox/start/end `change` listeners inside `renderDays()`'s per-row
loop with:

```js
      function weekdayPayload() {
        return {
          schedule_id: data.selectedId,
          day_of_week: day.day_of_week,
          enabled: day.enabled ? 1 : '',
          start_time: day.start_time,
          end_time: day.end_time
        };
      }

      function saveWeekday(revert) {
        FCS.post('fcs_save_schedule_weekday', weekdayPayload()).done((response) => {
          if (!response.success) {
            revert();
            renderDays();
            FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveDay), 'error');
            return;
          }
          FCS.toast(window.fcsAdmin.i18n.weekdaySaved);
        });
      }

      checkbox.addEventListener('change', () => {
        const previousEnabled = day.enabled;
        day.enabled = checkbox.checked;
        renderDays();
        saveWeekday(() => { day.enabled = previousEnabled; });
      });

      startInput.addEventListener('change', () => {
        const previous = day.start_time;
        day.start_time = `${startInput.value}:00`;
        if (!validateTimes()) return;
        saveWeekday(() => { day.start_time = previous; });
      });

      endInput.addEventListener('change', () => {
        const previous = day.end_time;
        day.end_time = `${endInput.value}:00`;
        if (!validateTimes()) return;
        saveWeekday(() => { day.end_time = previous; });
      });
```

(`weekdayPayload()`/`saveWeekday()` are declared once per row inside the
`data.weekdays.forEach((day, index) => { ... })` closure, same scope the existing
`checkbox`/`startInput`/`endInput`/`validateTimes` constants already live in, so
`day` resolves to that row's object exactly as it does today.)

- [ ] **Step 4: Make destination add/remove save instantly**

Replace the `addDestinationBtn` click handler with:

```js
  document.getElementById('addDestinationBtn')?.addEventListener('click', () => {
    const selected = catalogSelect.selectedOptions[0];
    if (!selected) return;

    const option = catalog.find(o => o.type === selected.dataset.type && o.key === selected.dataset.key);
    if (!option || option.assignedTo) return;

    const previous = destinations.slice();
    destinations.push({ type: option.type, key: option.key, label: option.label, available: true });
    renderDestinations();
    renderCatalogOptions();

    FCS.post('fcs_save_schedule_destinations', destinationsPayload()).done((response) => {
      if (!response.success) {
        destinations = previous;
        renderDestinations();
        renderCatalogOptions();
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveDestinations), 'error');
        return;
      }
      FCS.toast(window.fcsAdmin.i18n.destinationAdded);
    });
  });
```

and, inside `renderDestinations()`, replace the remove-button wiring with:

```js
    destList.querySelectorAll('[data-index]').forEach(btn => {
      btn.onclick = () => {
        const previous = destinations.slice();
        destinations.splice(Number(btn.dataset.index), 1);
        renderDestinations();
        renderCatalogOptions();

        FCS.post('fcs_save_schedule_destinations', destinationsPayload()).done((response) => {
          if (!response.success) {
            destinations = previous;
            renderDestinations();
            renderCatalogOptions();
            FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveDestinations), 'error');
            return;
          }
          FCS.toast(window.fcsAdmin.i18n.destinationRemoved);
        });
      };
    });
```

- [ ] **Step 5: Make the schedule name save on blur**

Replace:

```js
  if (titleInput) {
    titleInput.addEventListener('input', () => FCS.State.markDirty());
  }
```

with:

```js
  if (titleInput) {
    let savedName = titleInput.value;

    titleInput.addEventListener('blur', () => {
      const name = titleInput.value.trim();
      if (name === savedName) return;

      FCS.post('fcs_save_schedule', { schedule_id: data.selectedId, name }).done((response) => {
        if (!response.success) {
          titleInput.value = savedName;
          FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotSaveScheduleName), 'error');
          return;
        }

        savedName = response.data.name;
        titleInput.value = response.data.name;

        const sidebarTitle = document.querySelector(`a[href$="schedule_id=${data.selectedId}"] .fcs-schedule-nav__title`);
        if (sidebarTitle) sidebarTitle.textContent = response.data.name;
      });
    });
  }
```

(saves on blur, not on every keystroke; reverts to the last saved name on failure;
also fixes a pre-existing staleness bug where the sidebar's own copy of the name
never updated after a rename.)

- [ ] **Step 6: Wire the sidebar "⋮" menu and retarget delete to whichever schedule was chosen**

Replace the existing delete-modal open/confirm wiring:

```js
  document.getElementById('deleteScheduleBtn')?.addEventListener('click', openDeleteModal);
  cancelDeleteBtn?.addEventListener('click', closeDeleteModal);
  document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
    FCS.post('fcs_delete_schedule', { schedule_id: data.selectedId }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotDeleteSchedule), 'error');
        return;
      }
      window.location.href = data.baseUrl;
    });
  });
```

with:

```js
  let pendingDeleteId = data.selectedId;

  function closeAllScheduleMenus() {
    document.querySelectorAll('.fcs-schedule-nav__menu--show').forEach((menu) => menu.classList.remove('fcs-schedule-nav__menu--show'));
    document.querySelectorAll('.fcs-schedule-nav__more[aria-expanded="true"]').forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
  }

  document.querySelectorAll('.fcs-schedule-nav__more').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const menu = btn.nextElementSibling;
      const isOpen = menu.classList.contains('fcs-schedule-nav__menu--show');
      closeAllScheduleMenus();
      if (!isOpen) {
        menu.classList.add('fcs-schedule-nav__menu--show');
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  document.addEventListener('click', closeAllScheduleMenus);

  document.querySelectorAll('[data-action="delete-schedule"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      closeAllScheduleMenus();
      pendingDeleteId = Number(btn.dataset.scheduleId);
      openDeleteModal();
    });
  });

  document.getElementById('deleteScheduleBtn')?.addEventListener('click', () => {
    pendingDeleteId = data.selectedId;
    openDeleteModal();
  });

  cancelDeleteBtn?.addEventListener('click', closeDeleteModal);

  document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
    FCS.post('fcs_delete_schedule', { schedule_id: pendingDeleteId }).done((response) => {
      if (!response.success) {
        FCS.toast(saveMessage(response, window.fcsAdmin.i18n.couldNotDeleteSchedule), 'error');
        return;
      }

      closeDeleteModal();

      if (pendingDeleteId === data.selectedId) {
        window.location.href = data.baseUrl;
        return;
      }

      document.querySelector(`[data-schedule-id="${pendingDeleteId}"]`)?.closest('.fcs-schedule-nav__item')?.remove();
      FCS.toast(window.fcsAdmin.i18n.scheduleDeleted);
    });
  });
```

(`openDeleteModal`/`closeDeleteModal` and the existing keydown/focus-trap wiring on
`deleteModal` are untouched — they already work regardless of what set
`deleteModalTrigger`.)

- [ ] **Step 7: Verify**

Manually exercise every control on the Schedules screen: toggle a weekday (check the
toast, then reload the page to confirm it persisted), change a time, add and remove a
destination, rename the schedule (blur the field, reload to confirm), delete a
*different* schedule from its sidebar "⋮" menu (row should vanish immediately, no
navigation), and delete the *currently open* schedule from the top button (should
redirect, same as before). Also confirm a rejected change reverts: temporarily set an
end time before the start time to see the existing client-side validation still block
the request entirely (no round-trip), and — if convenient — simulate a server
rejection (e.g. an already-assigned destination) to confirm the toast fires and the
UI reverts. Run `./scripts/dev phpcs` on both touched files.

- [ ] **Step 8: Commit**

```bash
git add plugin/assets/admin/js/schedules.js plugin/src/Admin/Assets.php
git commit -m "feat(schedules): save every action instantly instead of behind a Save button"
```

---

### Task 7: Update `docs/technical/data-layer.md`

**Files:**
- Modify: `docs/technical/data-layer.md`

`docs/architecture.md` is out of scope here — per `docs/README.md`'s own mapping
table it covers only "the two-tree split, how the plugin autoloads, and how
dependencies are scoped for release," not admin-screen behaviour. The shared-blackout
ajax registration this plan changes is already documented in
`docs/technical/data-layer.md:170-175`.

- [ ] **Step 1: Update the shared-ajax-action note**

Replace (`docs/technical/data-layer.md:170-175`):

```
- A controller's own ajax actions are registered in its `register()` method, called from
  `Admin\Provider::boot()` - not gated to when its own screen is being viewed, since an
  ajax request to `admin-ajax.php` carries no "current screen". Two screens sharing one
  underlying resource (the Schedules screen's local blackouts and the Settings screen's
  store-wide ones) share one registered action rather than each registering the same
  `wp_ajax_*` hook, which would run both callbacks on every request.
```

with:

```
- A controller's own ajax actions are registered in its `register()` method, called from
  `Admin\Provider::boot()` - not gated to when its own screen is being viewed, since an
  ajax request to `admin-ajax.php` carries no "current screen". Two screens sharing one
  underlying resource (the Schedules screen's local blackouts and the Closures screen's
  store-wide ones) share one registered action rather than each registering the same
  `wp_ajax_*` hook, which would run both callbacks on every request.
```

- [ ] **Step 2: Commit**

```bash
git add docs/technical/data-layer.md
git commit -m "docs(technical): point the shared-blackout-ajax note at Closures, not Settings"
```

---

### Task 8: Final verification and PR

- [ ] **Step 1: Full test suite**

Run: `./scripts/dev test`
Expected: 240 tests passing, 0 failures.

- [ ] **Step 2: Full lint**

Run: `./scripts/dev phpcs` and `./scripts/dev phpstan` (each checks the whole
`plugin/` tree, not just the files touched in this plan). Expected: clean.

- [ ] **Step 3: Manual smoke test of all three screens**

Settings: Save Changes still works for cutoff/checkout-fields/subscribe-discount.
Schedules: every action instant per Task 6's Step 7 checklist. Closures: calendar
CRUD works exactly as it did on the old Settings tab.

- [ ] **Step 4: Push and open a PR**

```bash
git push -u origin feat/instant-admin-saves
gh pr create --title "Make schedule/closure edits save instantly, split closures onto their own page" --body "$(cat <<'EOF'
## Summary
- Weekday, destination, and schedule-name edits on the Schedules screen now save the moment they're made instead of waiting for a Save Schedule button, which is removed.
- Global closures move off Settings onto their own new "Closures" page, behaving exactly as they already did (Settings keeps its Save Changes button for its own fields).
- A schedule can now be deleted from its sidebar row's "⋮" menu without opening it; the in-panel Delete Schedule button moves next to the title; the delete-confirmation copy is a single plain sentence.

## Test plan
- [x] `./scripts/dev test`
- [x] Manual verification of Settings, Schedules and Closures screens per the plan's Task 8 checklist.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Report the PR URL.
