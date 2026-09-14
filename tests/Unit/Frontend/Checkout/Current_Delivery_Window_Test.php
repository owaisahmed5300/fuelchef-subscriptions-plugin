<?php
/**
 * Unit tests for the current checkout delivery window resolver.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Frontend\Checkout;

use Brain\Monkey\Functions;
use DateTimeZone;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Frontend\Checkout\Current_Delivery_Window;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Services\Availability_Service;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Tests\Unit\Repositories\Repository_TestCase;
use FuelChef\Subscriptions\WooCommerce\Chosen_Shipping_Destination;
use FuelChef\Subscriptions\WooCommerce\Destination_Catalog;

/**
 * @covers \FuelChef\Subscriptions\Frontend\Checkout\Current_Delivery_Window
 */
final class Current_Delivery_Window_Test extends Repository_TestCase {


	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, mixed $default = false ): mixed => 'time_format' === $key ? 'g:i a' : []
		);
	}

	/**
	 * @param list<array<string, mixed>> $weekday_rows
	 */
	private function window( array $weekday_rows ): Current_Delivery_Window {
		$weekday_wpdb = $this->wpdb();
		$weekday_wpdb->shouldReceive( 'get_results' )->andReturn( $weekday_rows );

		$availability = new Availability_Service(
			new Schedule_Weekday_Repository( $weekday_wpdb, $this->clock() ),
			new Blackout_Repository( $this->wpdb(), $this->clock() ),
			new Settings_Store(),
			$this->clock(),
			new Schedule_Destination_Repository( $this->wpdb(), $this->clock() ),
			new Schedule_Repository( $this->wpdb(), $this->clock() )
		);

		return new Current_Delivery_Window(
			new Chosen_Shipping_Destination( new Destination_Catalog() ),
			$availability,
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
}
