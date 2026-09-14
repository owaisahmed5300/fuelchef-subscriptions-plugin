<?php
/**
 * Unit tests for the schedule service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Services\Schedule_Service;
use FuelChef\Subscriptions\Tests\Unit\Repositories\Repository_TestCase;
use Mockery;

/**
 * @covers \FuelChef\Subscriptions\Services\Schedule_Service
 */
final class Schedule_Service_Test extends Repository_TestCase {


	private function service(
		?Schedule_Repository $schedules = null,
		?Schedule_Weekday_Repository $weekdays = null,
		?Blackout_Repository $blackouts = null,
		?Schedule_Destination_Repository $destinations = null
	): Schedule_Service {
		return new Schedule_Service(
			$schedules ?? new Schedule_Repository( $this->wpdb(), $this->clock() ),
			$weekdays ?? new Schedule_Weekday_Repository( $this->wpdb(), $this->clock() ),
			$blackouts ?? new Blackout_Repository( $this->wpdb(), $this->clock() ),
			$destinations ?? new Schedule_Destination_Repository( $this->wpdb(), $this->clock() )
		);
	}

	public function test_create_seeds_all_seven_weekdays_disabled_at_noon(): void {
		$schedule_wpdb            = $this->wpdb();
		$schedule_wpdb->insert_id = 4;
		$schedule_wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$weekday_wpdb = $this->wpdb();
		$weekday_wpdb->shouldReceive( 'insert' )->times( 7 )->with(
			'wp_fcs_schedule_weekdays',
			Mockery::on(
				static fn ( array $data ): bool => 4 === $data['schedule_id']
					&& 0 === $data['enabled']
					&& '12:00:00' === $data['start_time']
					&& '17:00:00' === $data['end_time']
			)
		)->andReturn( 1 );
		$weekday_wpdb->insert_id = 1;

		$schedule = $this->service(
			new Schedule_Repository( $schedule_wpdb, $this->clock() ),
			new Schedule_Weekday_Repository( $weekday_wpdb, $this->clock() )
		)->create( 'Karachi Delivery' );

		$this->assertSame( 4, $schedule->id() );
	}

	public function test_create_rejects_a_blank_name(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service()->create( '   ' );
	}

	public function test_rename_updates_the_name(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			[
				'id'           => '4',
				'name'         => 'Old Name',
				'date_created' => '2026-01-01 00:00:00',
				'date_updated' => '2026-01-01 00:00:00',
			]
		);
		$wpdb->shouldReceive( 'update' )->once()->with(
			'wp_fcs_schedules',
			Mockery::on( static fn ( array $data ): bool => 'New Name' === $data['name'] ),
			[ 'id' => 4 ]
		)->andReturn( 1 );

		$schedule = $this->service( new Schedule_Repository( $wpdb, $this->clock() ) )->rename( 4, 'New Name' );

		$this->assertSame( 'New Name', $schedule->name() );
	}

	public function test_rename_rejects_a_blank_name(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service()->rename( 4, '' );
	}

	public function test_update_weekday_updates_the_matching_day(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'           => '10',
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
		$wpdb->shouldReceive( 'update' )->once()->with(
			'wp_fcs_schedule_weekdays',
			Mockery::on(
				static fn ( array $data ): bool => 1 === $data['enabled']
					&& '09:00:00' === $data['start_time']
					&& '18:00:00' === $data['end_time']
			),
			[ 'id' => 10 ]
		)->andReturn( 1 );

		$weekday = $this->service( null, new Schedule_Weekday_Repository( $wpdb, $this->clock() ) )
			->update_weekday( 4, 1, true, '09:00:00', '18:00:00' );

		$this->assertTrue( $weekday->enabled() );
		$this->assertSame( '09:00:00', $weekday->start_time() );
		$this->assertSame( '18:00:00', $weekday->end_time() );
	}

	public function test_update_weekday_rejects_an_invalid_start_time(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service()->update_weekday( 4, 1, true, 'not-a-time', '17:00:00' );
	}

	public function test_update_weekday_rejects_an_invalid_end_time(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service()->update_weekday( 4, 1, true, '09:00:00', 'not-a-time' );
	}

	public function test_update_weekday_rejects_an_end_time_that_is_not_after_the_start_time(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service()->update_weekday( 4, 1, true, '09:00:00', '09:00:00' );
	}

	public function test_update_weekday_rejects_a_day_the_schedule_has_no_row_for(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

		Functions\when( 'esc_html__' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service( null, new Schedule_Weekday_Repository( $wpdb, $this->clock() ) )
			->update_weekday( 4, 1, true, '09:00:00', '17:00:00' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function weekday_row( string $id, int $day_of_week ): array {
		return [
			'id'           => $id,
			'schedule_id'  => '4',
			'day_of_week'  => (string) $day_of_week,
			'enabled'      => '1' === $id ? '1' : '0',
			'start_time'   => '1' === $id ? '09:00:00' : '10:00:00',
			'end_time'     => '1' === $id ? '17:00:00' : '15:00:00',
			'date_created' => '2026-01-01 00:00:00',
			'date_updated' => '2026-01-01 00:00:00',
		];
	}

	public function test_copy_weekday_to_days_below_copies_hours_and_enabled_state_only_to_later_days(): void {
		// Site week starts on Sunday (0); Monday (1) is the source, so only days 2-6 below
		// it are updated - Sunday (0), which is "above" it in site order, is untouched.
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->andReturn(
			[
				$this->weekday_row( '10', 0 ),
				$this->weekday_row( '1', 1 ),
				$this->weekday_row( '12', 2 ),
				$this->weekday_row( '13', 3 ),
				$this->weekday_row( '14', 4 ),
				$this->weekday_row( '15', 5 ),
				$this->weekday_row( '16', 6 ),
			]
		);
		Functions\when( 'get_option' )->justReturn( 0 );
		$wpdb->shouldReceive( 'update' )->times( 5 )->with(
			'wp_fcs_schedule_weekdays',
			Mockery::on(
				static fn ( array $data ): bool => 1 === $data['enabled']
					&& '09:00:00' === $data['start_time']
					&& '17:00:00' === $data['end_time']
			),
			Mockery::on( static fn ( array $where ): bool => in_array( $where['id'], [ 12, 13, 14, 15, 16 ], true ) )
		)->andReturn( 1 );

		$updated = $this->service( null, new Schedule_Weekday_Repository( $wpdb, $this->clock() ) )
			->copy_weekday_to_days_below( 4, 1 );

		$this->assertSame( [ 2, 3, 4, 5, 6 ], array_map( static fn ( $weekday ) => $weekday->day_of_week(), $updated ) );
	}

	public function test_copy_weekday_to_days_below_does_nothing_for_the_last_day_in_site_order(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->andReturn(
			[
				[
					'id'           => '10',
					'schedule_id'  => '4',
					'day_of_week'  => '6',
					'enabled'      => '1',
					'start_time'   => '09:00:00',
					'end_time'     => '17:00:00',
					'date_created' => '2026-01-01 00:00:00',
					'date_updated' => '2026-01-01 00:00:00',
				],
			]
		);
		Functions\when( 'get_option' )->justReturn( 0 );

		$updated = $this->service( null, new Schedule_Weekday_Repository( $wpdb, $this->clock() ) )
			->copy_weekday_to_days_below( 4, 6 );

		$this->assertSame( [], $updated );
	}

	public function test_copy_weekday_to_days_below_rejects_a_day_the_schedule_has_no_row_for(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

		Functions\when( 'esc_html__' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service( null, new Schedule_Weekday_Repository( $wpdb, $this->clock() ) )
			->copy_weekday_to_days_below( 4, 1 );
	}

	public function test_delete_cascades_destinations_then_weekdays_then_blackouts_then_the_schedule(): void {
		$order = [];

		$destinations_wpdb = $this->wpdb();
		$destinations_wpdb->shouldReceive( 'get_results' )->once()->andReturnUsing(
			static function () use ( &$order ): array {
				$order[] = 'destinations';

				return [];
			}
		);

		$weekdays_wpdb = $this->wpdb();
		$weekdays_wpdb->shouldReceive( 'get_results' )->once()->andReturnUsing(
			static function () use ( &$order ): array {
				$order[] = 'weekdays';

				return [];
			}
		);

		$blackouts_wpdb = $this->wpdb();
		$blackouts_wpdb->shouldReceive( 'get_results' )->once()->andReturnUsing(
			static function () use ( &$order ): array {
				$order[] = 'blackouts';

				return [];
			}
		);

		$schedules_wpdb = $this->wpdb();
		$schedules_wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$schedules_wpdb->shouldReceive( 'delete' )->once()->andReturnUsing(
			static function () use ( &$order ): int {
				$order[] = 'schedule';

				return 1;
			}
		);

		$this->service(
			new Schedule_Repository( $schedules_wpdb, $this->clock() ),
			new Schedule_Weekday_Repository( $weekdays_wpdb, $this->clock() ),
			new Blackout_Repository( $blackouts_wpdb, $this->clock() ),
			new Schedule_Destination_Repository( $destinations_wpdb, $this->clock() )
		)->delete( 4 );

		$this->assertSame( [ 'destinations', 'weekdays', 'blackouts', 'schedule' ], $order );
	}
}
