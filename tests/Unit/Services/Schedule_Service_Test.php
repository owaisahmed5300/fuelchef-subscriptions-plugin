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
					'date_created' => '2026-01-01 00:00:00',
					'date_updated' => '2026-01-01 00:00:00',
				],
			]
		);
		$wpdb->shouldReceive( 'update' )->once()->with(
			'wp_fcs_schedule_weekdays',
			Mockery::on(
				static fn ( array $data ): bool => 1 === $data['enabled'] && '09:00:00' === $data['start_time']
			),
			[ 'id' => 10 ]
		)->andReturn( 1 );

		$weekday = $this->service( null, new Schedule_Weekday_Repository( $wpdb, $this->clock() ) )
			->update_weekday( 4, 1, true, '09:00:00' );

		$this->assertTrue( $weekday->enabled() );
		$this->assertSame( '09:00:00', $weekday->start_time() );
	}

	public function test_update_weekday_rejects_an_invalid_time(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service()->update_weekday( 4, 1, true, 'not-a-time' );
	}

	public function test_update_weekday_rejects_a_day_the_schedule_has_no_row_for(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

		Functions\when( 'esc_html__' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service( null, new Schedule_Weekday_Repository( $wpdb, $this->clock() ) )
			->update_weekday( 4, 1, true, '09:00:00' );
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
