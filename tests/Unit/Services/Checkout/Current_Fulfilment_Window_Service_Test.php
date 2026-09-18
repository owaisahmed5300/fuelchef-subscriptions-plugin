<?php
/**
 * Unit tests for the current checkout fulfilment window resolver.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services\Checkout;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Services\Checkout\Chosen_Shipping_Destination_Service;
use FuelChef\Subscriptions\Services\Checkout\Current_Fulfilment_Window_Service;
use FuelChef\Subscriptions\Services\Scheduling\Availability_Service;
use FuelChef\Subscriptions\Services\Scheduling\Destination_Catalog_Service;
use FuelChef\Subscriptions\Services\Settings_Service;
use FuelChef\Subscriptions\Tests\Unit\Repositories\Repository_TestCase;

/**
 * @covers \FuelChef\Subscriptions\Services\Checkout\Current_Fulfilment_Window_Service
 */
final class Current_Fulfilment_Window_Service_Test extends Repository_TestCase {


	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, mixed $default = false ): mixed => 'time_format' === $key ? 'g:i a' : []
		);
	}

	/**
	 * @param list<array<string, mixed>> $weekday_rows
	 */
	private function window( array $weekday_rows ): Current_Fulfilment_Window_Service {
		$weekday_wpdb = $this->wpdb();
		$weekday_wpdb->shouldReceive( 'get_results' )->andReturn( $weekday_rows );

		$availability = new Availability_Service(
			new Schedule_Weekday_Repository( $weekday_wpdb, $this->clock() ),
			new Blackout_Repository( $this->wpdb(), $this->clock() ),
			new Settings_Service(),
			$this->clock(),
			new Schedule_Destination_Repository( $this->wpdb(), $this->clock() ),
			new Schedule_Repository( $this->wpdb(), $this->clock() )
		);

		return new Current_Fulfilment_Window_Service(
			new Chosen_Shipping_Destination_Service( new Destination_Catalog_Service() ),
			$availability,
			new Settings_Service(),
			$this->clock()
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function weekday_row( int $day_of_week, string $start_time, string $end_time ): array {
		return [
			'id'           => (string) ( $day_of_week + 1 ),
			'schedule_id'  => '4',
			'day_of_week'  => (string) $day_of_week,
			'enabled'      => '1',
			'start_time'   => $start_time,
			'end_time'     => $end_time,
			'date_created' => '2026-01-01 00:00:00',
			'date_updated' => '2026-01-01 00:00:00',
		];
	}

	public function test_windows_for_dates_formats_each_dates_hours(): void {
		// 2026-09-14 is a Monday (day_of_week 1).
		$window   = $this->window( [ $this->weekday_row( 1, '09:00:00', '17:00:00' ) ] );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$this->assertSame(
			[
				'2026-09-14' => [
					'start' => '9:00 am',
					'end'   => '5:00 pm',
				],
			],
			$window->windows_for_dates( $schedule, [ '2026-09-14' ] )
		);
	}

	public function test_windows_for_dates_skips_dates_whose_weekday_is_not_open(): void {
		// 2026-09-14 is a Monday (day_of_week 1); Sunday (0) has no row at all.
		$window   = $this->window( [ $this->weekday_row( 1, '09:00:00', '17:00:00' ) ] );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$this->assertSame(
			[ '2026-09-14' => [ 'start' => '9:00 am', 'end' => '5:00 pm' ] ],
			$window->windows_for_dates( $schedule, [ '2026-09-13', '2026-09-14' ] )
		);
	}

	public function test_windows_for_dates_is_empty_for_an_empty_date_list(): void {
		$window   = $this->window( [] );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$this->assertSame( [], $window->windows_for_dates( $schedule, [] ) );
	}

	public function test_eligible_dates_is_bounded_by_the_configured_max_fulfilment_window(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, mixed $default = false ): mixed => match ( $key ) {
				'time_format' => 'g:i a',
				default => [
					'max_fulfilment_window_days' => 2,
					'cutoff_days'                => 0,
					'cutoff_time'                => '00:00:00',
				],
			}
		);

		$weekday_wpdb = $this->wpdb();
		$weekday_wpdb->shouldReceive( 'get_results' )->andReturn(
			array_map( fn ( int $day ): array => $this->weekday_row( $day, '00:00:00', '23:59:59' ), range( 0, 6 ) )
		);

		$blackout_wpdb = $this->wpdb();
		$blackout_wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$availability = new Availability_Service(
			new Schedule_Weekday_Repository( $weekday_wpdb, $this->clock() ),
			new Blackout_Repository( $blackout_wpdb, $this->clock() ),
			new Settings_Service(),
			$this->clock(),
			new Schedule_Destination_Repository( $this->wpdb(), $this->clock() ),
			new Schedule_Repository( $this->wpdb(), $this->clock() )
		);

		$window = new Current_Fulfilment_Window_Service(
			new Chosen_Shipping_Destination_Service( new Destination_Catalog_Service() ),
			$availability,
			new Settings_Service(),
			$this->clock()
		);

		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );
		$dates    = $window->eligible_dates( $schedule );

		$latest_possible = ( new DateTimeImmutable( 'today', new DateTimeZone( 'UTC' ) ) )
			->modify( '+2 days' )
			->format( 'Y-m-d' );

		$this->assertNotEmpty( $dates );
		$this->assertLessThanOrEqual( 3, count( $dates ) );
		$this->assertSame( $latest_possible, end( $dates ) );
	}

	public function test_is_eligible_date_matches_the_schedules_eligible_dates(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, mixed $default = false ): mixed => match ( $key ) {
				'time_format' => 'g:i a',
				default => [
					'max_fulfilment_window_days' => 2,
					'cutoff_days'                => 0,
					'cutoff_time'                => '00:00:00',
				],
			}
		);

		$weekday_wpdb = $this->wpdb();
		$weekday_wpdb->shouldReceive( 'get_results' )->andReturn(
			array_map( fn ( int $day ): array => $this->weekday_row( $day, '00:00:00', '23:59:59' ), range( 0, 6 ) )
		);

		$blackout_wpdb = $this->wpdb();
		$blackout_wpdb->shouldReceive( 'get_results' )->andReturn( [] );

		$availability = new Availability_Service(
			new Schedule_Weekday_Repository( $weekday_wpdb, $this->clock() ),
			new Blackout_Repository( $blackout_wpdb, $this->clock() ),
			new Settings_Service(),
			$this->clock(),
			new Schedule_Destination_Repository( $this->wpdb(), $this->clock() ),
			new Schedule_Repository( $this->wpdb(), $this->clock() )
		);

		$window = new Current_Fulfilment_Window_Service(
			new Chosen_Shipping_Destination_Service( new Destination_Catalog_Service() ),
			$availability,
			new Settings_Service(),
			$this->clock()
		);

		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );
		// With a same-day, midnight cutoff, today's own deadline has always already
		// passed by the time "now" is anything after midnight - tomorrow is the nearest
		// date that can still be eligible.
		$tomorrow = ( new DateTimeImmutable( 'today', new DateTimeZone( 'UTC' ) ) )
			->modify( '+1 day' )
			->format( 'Y-m-d' );
		$too_far  = ( new DateTimeImmutable( 'today', new DateTimeZone( 'UTC' ) ) )
			->modify( '+10 days' )
			->format( 'Y-m-d' );

		$this->assertTrue( $window->is_eligible_date( $schedule, $tomorrow ) );
		$this->assertFalse( $window->is_eligible_date( $schedule, $too_far ) );
	}
}
