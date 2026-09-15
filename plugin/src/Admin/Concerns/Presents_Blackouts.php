<?php
/**
 * Blackout presentation trait.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Admin\Concerns;

use FuelChef\Subscriptions\Entities\Blackout;

defined( 'ABSPATH' ) || exit;

/**
 * Shapes blackouts for the calendar script, shared by the Settings and Schedules screens.
 */
trait Presents_Blackouts {


	/**
	 * Shapes a list of blackouts for the calendar script.
	 *
	 * @param list<Blackout> $blackouts Blackouts to shape.
	 *
	 * @return list<array{id: int|null, date: string, label: string, reason: string|null}> The shaped blackouts.
	 */
	private function blackouts_for_js( array $blackouts ): array {
		return array_map(
			static fn ( Blackout $blackout ): array => [
				'id'     => $blackout->id(),
				'date'   => $blackout->date(),
				'label'  => gmdate( 'M d, Y', (int) strtotime( $blackout->date() ) ),
				'reason' => $blackout->reason(),
			],
			$blackouts
		);
	}
}
