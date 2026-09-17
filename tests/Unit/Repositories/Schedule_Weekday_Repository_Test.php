<?php
/**
 * Unit tests for the schedule weekday repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Repositories;

use FuelChef\Subscriptions\Entities\Schedule_Weekday;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use Mockery;

/**
 * @covers \FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository
 */
final class Schedule_Weekday_Repository_Test extends Repository_TestCase {


	public function test_find_by_schedule_hydrates_every_row(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'           => '1',
					'schedule_id'  => '4',
					'day_of_week'  => '1',
					'enabled'      => '1',
					'start_time'   => '09:00:00',
					'end_time'     => '17:00:00',
					'date_created' => '2026-01-01 00:00:00',
					'date_updated' => '2026-01-01 00:00:00',
				],
			]
		);

		$repository = new Schedule_Weekday_Repository( $wpdb, $this->clock() );
		$weekdays   = $repository->find_by_schedule( 4 );

		$this->assertCount( 1, $weekdays );
		$this->assertSame( 4, $weekdays[0]->schedule_id() );
		$this->assertSame( 1, $weekdays[0]->day_of_week() );
		$this->assertTrue( $weekdays[0]->enabled() );
		$this->assertSame( '09:00:00', $weekdays[0]->start_time() );
		$this->assertSame( '17:00:00', $weekdays[0]->end_time() );
	}

	public function test_find_by_schedule_caches_the_list_per_schedule(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

		$repository = new Schedule_Weekday_Repository( $wpdb, $this->clock() );

		$repository->find_by_schedule( 4 );
		$repository->find_by_schedule( 4 );
	}

	public function test_a_different_schedule_is_not_served_from_another_schedules_cache(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( [] );

		$repository = new Schedule_Weekday_Repository( $wpdb, $this->clock() );

		$repository->find_by_schedule( 4 );
		$repository->find_by_schedule( 5 );
	}

	public function test_updating_a_weekday_invalidates_its_schedules_cached_list(): void {
		$row = [
			'id'           => '1',
			'schedule_id'  => '4',
			'day_of_week'  => '1',
			'enabled'      => '0',
			'start_time'   => '12:00:00',
			'end_time'     => '17:00:00',
			'date_created' => '2026-01-01 00:00:00',
			'date_updated' => '2026-01-01 00:00:00',
		];

		$wpdb = $this->wpdb();
		// Once for the cache-priming call, once more after update() invalidates it.
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( [ $row ] );
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$repository = new Schedule_Weekday_Repository( $wpdb, $this->clock() );

		$weekday = $repository->find_by_schedule( 4 )[0];
		$weekday->set_enabled( true );
		$repository->update( $weekday );

		$repository->find_by_schedule( 4 );
	}

	public function test_delete_by_schedule_removes_every_row_for_that_schedule(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'           => '1',
					'schedule_id'  => '4',
					'day_of_week'  => '0',
					'enabled'      => '0',
					'start_time'   => '12:00:00',
					'end_time'     => '17:00:00',
					'date_created' => '2026-01-01 00:00:00',
					'date_updated' => '2026-01-01 00:00:00',
				],
				[
					'id'           => '2',
					'schedule_id'  => '4',
					'day_of_week'  => '1',
					'enabled'      => '0',
					'start_time'   => '12:00:00',
					'end_time'     => '17:00:00',
					'date_created' => '2026-01-01 00:00:00',
					'date_updated' => '2026-01-01 00:00:00',
				],
			]
		);
		$wpdb->shouldReceive( 'get_row' )->twice()->andReturn( null );
		$wpdb->shouldReceive( 'delete' )->twice()->andReturn( 1 );

		$repository = new Schedule_Weekday_Repository( $wpdb, $this->clock() );

		$repository->delete_by_schedule( 4 );
	}

	public function test_dehydrate_writes_enabled_as_an_integer_not_a_bool(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'insert' )->once()->with(
			'wp_fcs_schedule_weekdays',
			Mockery::on(
				static fn ( array $data ): bool => 1 === $data['enabled'] && is_int( $data['enabled'] )
			)
		)->andReturn( 1 );
		$wpdb->insert_id = 1;

		$repository = new Schedule_Weekday_Repository( $wpdb, $this->clock() );

		$repository->insert( new Schedule_Weekday( 4, 1, true, '09:00:00', '17:00:00' ) );
	}

	public function test_dehydrate_writes_the_end_time(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'insert' )->once()->with(
			'wp_fcs_schedule_weekdays',
			Mockery::on(
				static fn ( array $data ): bool => '17:00:00' === $data['end_time']
			)
		)->andReturn( 1 );
		$wpdb->insert_id = 1;

		$repository = new Schedule_Weekday_Repository( $wpdb, $this->clock() );

		$repository->insert( new Schedule_Weekday( 4, 1, true, '09:00:00', '17:00:00' ) );
	}
}
