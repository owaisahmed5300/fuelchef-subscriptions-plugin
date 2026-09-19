# Instant-save admin actions and a standalone Closures page: design

**Status:** draft — pending your review.
**Scope:** `Settings_Controller`, `Schedules_Controller`, `Admin\Menu`, `Admin\Assets`,
`Admin\Provider`, the Schedules and Settings templates/JS, and a new Closures
controller/screen. No change to `Schedule_Service`'s validation rules, the database
schema, or customer-facing (checkout/recurring-order) behaviour.

## Goals

1. Every action on the Schedules screen (weekday toggle/time, destination add/remove,
   schedule rename, local closure add/edit-note/remove) saves itself immediately, the
   moment the admin makes the change. No "Save Schedule" button, no dirty/unsaved state.
2. Global closures move off the Settings screen onto their own admin page ("Closures"),
   behaving exactly as they already do today (instant per-action save, toast feedback) —
   this is a relocation, not a behaviour change.
3. The Settings screen keeps its existing "Save Changes" button for the fields that
   actually belong to a form users review before committing (order cutoff, checkout
   copy, subscribe discount) — unchanged.
4. A schedule can be deleted from the sidebar list without opening it first, via a
   hover-revealed "⋮" menu next to each row's chevron.
5. The Delete Schedule button moves to the top of the schedule detail panel.
6. The delete-confirmation copy is generic, not an inventory of what gets removed.

## Out of scope

- Any change to `Schedule_Service` or `Blackout_Service` validation rules.
- Any change to the destinations conflict-check logic (`assign_destinations` keeps
  replacing the whole list and rejecting the batch on conflict — it is just called
  immediately now instead of at Save time).
- Redesigning the calendar/popover widget itself (`FCS.createCalendar` in `common.js`)
  — it is relocated, not rewritten.

## Menu and routing

New submenu, third in the list: **Settings → Schedules → Closures**.

- `Menu::CLOSURES_SLUG = 'fuelchef-closures'`.
- `Menu` gains a constructor dependency on the new `Closures_Controller` and one more
  `add_submenu_page()` call.
- `Admin\Assets::enqueue()`'s `$screen` match gains `Menu::CLOSURES_SLUG => 'closures'`,
  enqueuing `assets/admin/css/closures.css` and `assets/admin/js/closures.js`.
- `Admin\Provider` registers `Closures_Controller` as a singleton (dependencies:
  `Blackout_Service`, `Blackout_Repository`, `Renderer` — the same three
  `Settings_Controller` currently takes for this purpose) and calls its `register()`
  in `boot()`.

## Controllers

**`Settings_Controller`** — loses everything blackout-related: the `Blackout_Service`
and `Blackout_Repository` constructor parameters, the `Presents_Blackouts` trait,
`ajax_save_blackout()`, `ajax_delete_blackout()`, and their `register()` entries. Left
with `Settings_Service`, `Renderer`, `ajax_save_settings()`, and `render()` for the two
remaining tabs (Order Cutoff, Checkout Fields). No behaviour change to what remains.

**`Closures_Controller`** (new) — everything just removed from `Settings_Controller`
moves here verbatim: `Presents_Blackouts`, `Verifies_Ajax_Request`, `Blackout_Service`,
`Blackout_Repository`, `Renderer`; `render()` for the new page; `ajax_save_blackout()`
and `ajax_delete_blackout()`, registered under the same two action names
(`fcs_save_blackout`, `fcs_delete_blackout`) they already use today. Those two actions
stay shared with `Schedules_Controller`'s local-closures calendar, exactly as
`Settings_Controller` and `Schedules_Controller` share them today — only the owning
controller changes. Update the "shared" docblock note to point at the new owner.

**`Schedules_Controller`** — keeps `ajax_save_schedule` (create + rename, unchanged),
`ajax_delete_schedule` (unchanged, now also the target of the sidebar menu's delete),
`ajax_copy_schedule_weekday` (unchanged — it was never batched), and
`ajax_save_schedule_destinations` (unchanged endpoint; called immediately by JS instead
of at Save time). Loses `ajax_save_schedule_weekdays` (the whole-batch version). Gains
`ajax_save_schedule_weekday` (singular): reads one day's `day_of_week`, `enabled`,
`start_time`, `end_time`, calls the already-existing `Schedule_Service::update_weekday()`
(currently only called internally by the batch method), and returns that one row via
the existing private `weekday_for_js()` helper. `Schedule_Service::update_weekdays()`
(plural) is deleted along with its only caller.

## Final AJAX action inventory

| Action | Owner | Change |
|---|---|---|
| `fcs_save_settings` | Settings | unchanged |
| `fcs_save_schedule` | Schedules | unchanged (create + rename) |
| `fcs_delete_schedule` | Schedules | unchanged; now also called from the sidebar menu |
| `fcs_save_schedule_weekday` | Schedules | **new**, singular |
| `fcs_save_schedule_weekdays` | Schedules | **removed** |
| `fcs_copy_schedule_weekday` | Schedules | unchanged |
| `fcs_save_schedule_destinations` | Schedules | unchanged endpoint, now called per action |
| `fcs_save_blackout` | **Closures** (was Settings) | unchanged behaviour, moved |
| `fcs_delete_blackout` | **Closures** (was Settings) | unchanged behaviour, moved |

## Client-side interaction pattern (Schedules screen)

Every control follows the pattern the blackout calendar already uses: apply the change
locally and re-render immediately, fire the save in the background, and only on a
rejected response revert the control to its previous value and show an error toast. A
success toast confirms the save (new: weekday and destination changes get one now, for
the same feedback the blackout calendar already gives; matching i18n strings already
exist for most of these — `couldNotSaveDay`, `couldNotSaveDestinations`,
`couldNotSaveScheduleName` — new ones are added only for the weekday/destination
*success* toasts, which didn't need one while they were silently marked dirty).

- **Weekday toggle** — flip `day.enabled`, `renderDays()`, POST
  `fcs_save_schedule_weekday`. On failure, flip it back, `renderDays()`, toast.
- **Weekday start/end time** — existing client-side range validation runs first
  (unchanged); once valid, POST the same action. On failure, restore the previous time
  value, toast.
- **Copy to days below** — unchanged; already a single immediate AJAX call.
- **Destination add/remove** — mutate the local `destinations` array, re-render, POST
  the full list via `fcs_save_schedule_destinations` (reusing the existing
  `destinationsPayload()` builder, just invoked immediately instead of at Save time). On
  failure (e.g. a conflict), undo the mutation, re-render, toast.
- **Schedule name** — save on blur (not on every keystroke) via `fcs_save_schedule`. On
  failure (blank name), restore the previous name, toast.
- **Local closures** — unchanged; already this exact pattern.

`FCS.State` (the dirty-tracking/`beforeunload` module) is no longer used by
`schedules.js` at all — nothing on this screen is ever unsaved. It remains used by
`settings.js` for the fields still behind "Save Changes". The `saveScheduleBtn`,
`fcs-submit-bar`, and `fcsSaveStatus` markup are removed from `schedules.php`.

## Delete Schedule button placement

Moves from the bottom submit bar into the title row (`fcs-title-wrap`), next to the
schedule name input, so it's visible without scrolling.

## Sidebar delete-without-opening

Each `<li>` in `.fcs-schedule-nav` gains a "⋮" trigger next to the existing chevron,
visible on row hover/focus (keyboard-reachable, not hover-only). Clicking it opens a
small dropdown with one item, "Delete", styled as a destructive/red action. It opens the
*same* `#deleteModalOverlay` confirmation already on the page, targeting that row's
`schedule_id` rather than always `data.selectedId`. On confirm:

- If the deleted schedule **is** the currently open one: unchanged today's behaviour
  (redirect to the base Schedules URL).
- If it is **any other** schedule: remove its `<li>` from the sidebar in place and show
  a success toast. No navigation, no reload — "without opening the schedule" is the
  explicit requirement.

## Copy changes

Delete-confirmation modal body goes from itemizing what's removed to one plain
sentence:

- Before: *"Its weekdays, local closure dates and destination assignments are removed
  with it. This cannot be undone."*
- After: *"This cannot be undone."*

(Title "Delete this schedule?" and the red "Delete Permanently" button are unchanged.
Same copy is reused for the sidebar-menu confirmation — it's the same modal.)

## Testing impact

Controllers are view/glue code and are not unit-tested per this repo's established
policy (see the 2026-09-13 admin-scheduling-layer-design spec's "Testing" section) — no
`Schedules_ControllerTest`, `Settings_ControllerTest`, or `Closures_ControllerTest`
exist or are added. What changes for `Schedule_ServiceTest`: remove coverage of
`update_weekdays()` (deleted), keep the existing coverage of `update_weekday()`
(already exists, now reachable from a controller action directly rather than only via
the batch loop's internal call).

## Docs impact

`docs/architecture.md` and any admin-screen walkthrough that names the current two
screens or the batched-save behaviour needs updating to reflect three screens and the
instant-save model, per this repo's "change the docs describing any behaviour you
change, in the same PR" rule.
