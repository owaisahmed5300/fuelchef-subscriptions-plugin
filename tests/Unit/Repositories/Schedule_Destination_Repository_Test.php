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

	public function test_find_by_schedule_hits_the_database_only_once_across_repeated_calls(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

		$repository = new Schedule_Destination_Repository( $wpdb, $this->clock() );

		$repository->find_by_schedule( 4 );
		$repository->find_by_schedule( 4 );
	}

	public function test_find_by_destination_returns_every_schedule_assigned_to_it(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'               => '1',
					'schedule_id'      => '4',
					'destination_type' => 'pickup_location',
					'destination_key'  => 'central-depot',
					'date_created'     => '2026-01-01 00:00:00',
					'date_updated'     => '2026-01-01 00:00:00',
				],
			]
		);

		$repository = new Schedule_Destination_Repository( $wpdb, $this->clock() );
		$assigned   = $repository->find_by_destination( 'pickup_location', 'central-depot' );

		$this->assertCount( 1, $assigned );
		$this->assertSame( 4, $assigned[0]->schedule_id() );
	}

	public function test_find_by_destination_caches_the_list_per_destination_key(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( [] );

		$repository = new Schedule_Destination_Repository( $wpdb, $this->clock() );

		$repository->find_by_destination( 'pickup_location', 'central-depot' );
		$repository->find_by_destination( 'pickup_location', 'central-depot' );
		$repository->find_by_destination( 'shipping_zone', '2' );
	}

	public function test_inserting_a_destination_invalidates_its_destination_cache(): void {
		$wpdb = $this->wpdb();
		// Once for the cache-priming call, once more after insert() invalidates it.
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( [] );
		$wpdb->insert_id = 5;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$repository = new Schedule_Destination_Repository( $wpdb, $this->clock() );

		$repository->find_by_destination( 'pickup_location', 'central-depot' );
		$repository->insert( new Schedule_Destination( 4, 'pickup_location', 'central-depot' ) );
		$repository->find_by_destination( 'pickup_location', 'central-depot' );
	}

	public function test_deleting_a_destination_invalidates_its_schedules_cached_list(): void {
		$row = [
			'id'               => '1',
			'schedule_id'      => '4',
			'destination_type' => 'shipping_zone',
			'destination_key'  => '2',
			'date_created'     => '2026-01-01 00:00:00',
			'date_updated'     => '2026-01-01 00:00:00',
		];

		$wpdb = $this->wpdb();
		// Once for the cache-priming list, once more after delete() invalidates it.
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( [ $row ] );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $row );
		$wpdb->shouldReceive( 'delete' )->once()->andReturn( 1 );

		$repository = new Schedule_Destination_Repository( $wpdb, $this->clock() );

		$repository->find_by_schedule( 4 );
		$repository->delete( 1 );
		$repository->find_by_schedule( 4 );
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
