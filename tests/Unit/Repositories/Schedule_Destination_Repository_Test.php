<?php
/**
 * Unit tests for the schedule destination repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Repositories;

use FuelChef\Subscriptions\Entities\Schedule_Destination;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use Mockery;

/**
 * @covers \FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository
 */
final class Schedule_Destination_Repository_Test extends Repository_TestCase {


	public function test_find_by_schedule_hydrates_the_destination_type_and_key(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'               => '1',
					'schedule_id'      => '4',
					'destination_type' => 'shipping_zone',
					'destination_key'  => '2',
					'date_created'     => '2026-01-01 00:00:00',
					'date_updated'     => '2026-01-01 00:00:00',
				],
			]
		);

		$repository   = new Schedule_Destination_Repository( $wpdb, $this->clock() );
		$destinations = $repository->find_by_schedule( 4 );

		$this->assertSame( 'shipping_zone', $destinations[0]->destination_type() );
		$this->assertSame( '2', $destinations[0]->destination_key() );
	}

	public function test_replace_for_schedule_deletes_the_old_set_before_inserting_the_new_one(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'               => '1',
					'schedule_id'      => '4',
					'destination_type' => 'shipping_zone',
					'destination_key'  => '2',
					'date_created'     => '2026-01-01 00:00:00',
					'date_updated'     => '2026-01-01 00:00:00',
				],
			]
		);
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'delete' )->once()->andReturn( 1 );
		$wpdb->insert_id = 9;
		$wpdb->shouldReceive( 'insert' )->once()->with(
			'wp_fcs_schedule_destinations',
			Mockery::subset(
				[
					'destination_type' => 'pickup_location',
					'destination_key'  => 'central-depot',
				]
			)
		)->andReturn( 1 );

		$repository = new Schedule_Destination_Repository( $wpdb, $this->clock() );

		$repository->replace_for_schedule(
			4,
			[ new Schedule_Destination( 4, 'pickup_location', 'central-depot' ) ]
		);
	}
}
