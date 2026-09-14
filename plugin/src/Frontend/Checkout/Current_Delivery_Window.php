<?php
/**
 * Current checkout delivery window.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Frontend\Checkout;

use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Services\Availability_Service;
use FuelChef\Subscriptions\Utils\Clock;
use FuelChef\Subscriptions\Values\DateTime;
use FuelChef\Subscriptions\WooCommerce\Chosen_Shipping_Destination;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the schedule and eligible delivery dates for whatever destination the
 * customer currently has chosen at checkout.
 *
 * Shared between classic and block checkout's own delivery date field, since both ask
 * exactly this question the same way - only how each reads and renders the answer
 * differs.
 */
final class Current_Delivery_Window {


	/**
	 * How many days ahead eligible dates are offered for. Wide enough for a customer to
	 * plan a few weeks out, narrow enough that computing it on every checkout refresh
	 * stays cheap.
	 */
	private const LOOKAHEAD_DAYS = 60;

	/**
	 * Creates the resolver.
	 */
	public function __construct(
		private Chosen_Shipping_Destination $destination,
		private Availability_Service $availability,
		private Clock $clock
	) {
	}

	/**
	 * The schedule that applies to whatever destination the customer has currently
	 * chosen, or null when nothing chosen yet resolves to one.
	 */
	public function schedule(): ?Schedule {
		$destination = $this->destination->resolve();

		if ( null === $destination ) {
			return null;
		}

		return $this->availability->schedule_for_destination( $destination->type(), $destination->key() );
	}

	/**
	 * Every date the schedule can fulfil on within the lookahead window.
	 *
	 * @return list<string> The eligible dates, in order.
	 */
	public function eligible_dates( Schedule $schedule ): array {
		$today = $this->clock->now_wp();
		$from  = $today->format( DateTime::DATABASE_DATE_FORMAT );
		$to    = $today->native()
			->modify( sprintf( '+%d days', self::LOOKAHEAD_DAYS ) )
			->format( DateTime::DATABASE_DATE_FORMAT );

		return $this->availability->eligible_dates( $schedule, $from, $to );
	}
}
