<?php
/**
 * Blackout service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Services\Scheduling;

use FuelChef\Subscriptions\Entities\Blackout;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Utils\Str;
use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Adds, edits and removes blackout dates, global or schedule-scoped.
 */
final class Blackout_Service {


	/**
	 * Longest reason the schema's `reason` column accepts.
	 */
	private const MAX_REASON_LENGTH = 255;

	/**
	 * Creates the service.
	 */
	public function __construct(
		private Blackout_Repository $blackouts
	) {
	}

	/**
	 * Adds a blackout date, global or scoped to a schedule.
	 *
	 * @param int|null    $schedule_id Schedule ID, or null for store-wide.
	 * @param string      $date Calendar date to block, in `Y-m-d` form.
	 * @param string|null $reason Optional note explaining the closure.
	 *
	 * @throws Validation_Exception When the date is invalid, or already blacked out.
	 */
	public function add( ?int $schedule_id, string $date, ?string $reason = null ): Blackout {
		if ( ! DateTime::is_valid_date( $date ) ) {
			throw Validation_Exception::for_invalid_date( $date );
		}

		if ( $this->blackouts->exists_on_date( $schedule_id, $date ) ) {
			throw Validation_Exception::for_duplicate_date( $date );
		}

		return $this->blackouts->insert( new Blackout( $schedule_id, $date, $this->capped_reason( $reason ) ) );
	}

	/**
	 * Updates an existing blackout's reason.
	 *
	 * @param int         $blackout_id Blackout ID.
	 * @param string|null $reason New reason, or null to clear it.
	 */
	public function update_reason( int $blackout_id, ?string $reason ): Blackout {
		$blackout = $this->blackouts->find_or_fail( $blackout_id );

		$blackout->set_reason( $this->capped_reason( $reason ) );

		return $this->blackouts->update( $blackout );
	}

	/**
	 * Removes a blackout date.
	 */
	public function remove( int $blackout_id ): void {
		$this->blackouts->delete( $blackout_id );
	}

	/**
	 * Truncates a reason to the schema's column length.
	 *
	 * @param string|null $reason Raw reason.
	 */
	private function capped_reason( ?string $reason ): ?string {
		if ( null === $reason ) {
			return null;
		}

		return Str::length( $reason ) > self::MAX_REASON_LENGTH
			? Str::substr( $reason, 0, self::MAX_REASON_LENGTH )
			: $reason;
	}
}
