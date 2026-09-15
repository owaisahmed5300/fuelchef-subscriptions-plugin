<?php
/**
 * Unit tests for the availability service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services;

use Brain\Monkey\Functions;
use DateTimeZone;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Destination_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use FuelChef\Subscriptions\Repositories\Schedule_Weekday_Repository;
use FuelChef\Subscriptions\Services\Availability_Service;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Tests\Unit\Repositories\Repository_TestCase;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;

/**
 * @covers \FuelChef\Subscriptions\Services\Availability_Service
 */
final class Availability_Service_Test extends Repository_TestCase {


	/**
	 * @return array<string, mixed>
	 */
	private function weekday_row(
		int $schedule_id,
		int $day_of_week,
		bool $enabled,
		string $start_time = '12:00:00',
		string $end_time = '17:00:00'
	): array {
		return [
			'id'           => (string) ( $day_of_week + 1 ),
			'schedule_id'  => (string) $schedule_id,
			'day_of_week'  => (string) $day_of_week,
			'enabled'      => $enabled ? '1' : '0',
			'start_time'   => $start_time,
			'end_time'     => $end_time,
			'date_created' => '2026-01-01 00:00:00',
			'date_updated' => '2026-01-01 00:00:00',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function blackout_row( ?string $schedule_id, string $date ): array {
		return [
			'id'            => '1',
			'schedule_id'   => $schedule_id,
			'blackout_date' => $date,
			'reason'        => null,
			'date_created'  => '2026-01-01 00:00:00',
			'date_updated'  => '2026-01-01 00:00:00',
		];
	}

	/**
	 * Stubs get_option() so a real Settings_Store reports the given cutoff, and
	 * wp_timezone() so DateTime::from_wp() has a real timezone to resolve against.
	 */
	private function stub_cutoff( int $days, string $time ): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'cutoff_days'                => $days,
				'cutoff_time'                => $time,
				'subscribe_discount_percent' => 5,
				'subscribe_applicability'    => Subscribe_Applicability::INITIAL_AND_RENEWALS,
			]
		);

		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'UTC' ) );
		Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	/**
	 * @param list<array<string, mixed>> $weekday_rows
	 * @param list<array<string, mixed>> $blackout_rows
	 */
	private function service(
		array $weekday_rows = [],
		array $blackout_rows = [],
		int $cutoff_days = 0,
		string $cutoff_time = '00:00:00'
	): Availability_Service {
		$weekday_wpdb = $this->wpdb();
		$weekday_wpdb->shouldReceive( 'get_results' )->andReturn( $weekday_rows );

		$blackout_wpdb = $this->wpdb();
		$blackout_wpdb->shouldReceive( 'get_results' )->andReturn( $blackout_rows );

		$this->stub_cutoff( $cutoff_days, $cutoff_time );

		return new Availability_Service(
			new Schedule_Weekday_Repository( $weekday_wpdb, $this->clock() ),
			new Blackout_Repository( $blackout_wpdb, $this->clock() ),
			new Settings_Store(),
			$this->clock(),
			new Schedule_Destination_Repository( $this->wpdb(), $this->clock() ),
			new Schedule_Repository( $this->wpdb(), $this->clock() )
		);
	}

	public function test_weekday_for_returns_null_for_an_invalid_date(): void {
		$this->assertNull( $this->service()->weekday_for( 4, 'not-a-date' ) );
	}

	public function test_weekday_for_returns_null_when_the_day_is_disabled(): void {
		// 2026-09-14 is a Monday (day_of_week 1).
		$service = $this->service( [ $this->weekday_row( 4, 1, false ) ] );

		$this->assertNull( $service->weekday_for( 4, '2026-09-14' ) );
	}

	public function test_weekday_for_returns_the_row_when_the_day_is_enabled(): void {
		$service = $this->service( [ $this->weekday_row( 4, 1, true, '09:00:00' ) ] );

		$weekday = $service->weekday_for( 4, '2026-09-14' );

		$this->assertNotNull( $weekday );
		$this->assertSame( '09:00:00', $weekday->start_time() );
	}

	public function test_is_closed_is_true_for_a_global_blackout(): void {
		$service = $this->service( [], [ $this->blackout_row( null, '2026-12-25' ) ] );

		$this->assertTrue( $service->is_closed( 4, '2026-12-25' ) );
	}

	public function test_is_closed_is_false_outside_any_blackout(): void {
		$service = $this->service( [], [ $this->blackout_row( null, '2026-12-25' ) ] );

		$this->assertFalse( $service->is_closed( 4, '2026-12-26' ) );
	}

	public function test_open_dates_is_empty_when_no_weekday_is_enabled(): void {
		$service = $this->service( [ $this->weekday_row( 4, 1, false ) ] );

		$this->assertSame( [], $service->open_dates( 4, '2026-09-14', '2026-09-20' ) );
	}

	public function test_open_dates_includes_only_enabled_weekdays_in_range(): void {
		// 2026-09-14 Mon, 15 Tue, 16 Wed, 17 Thu, 18 Fri, 19 Sat, 20 Sun.
		$service = $this->service(
			[
				$this->weekday_row( 4, 1, true ), // Monday
				$this->weekday_row( 4, 3, true ), // Wednesday
			]
		);

		$this->assertSame(
			[ '2026-09-14', '2026-09-16' ],
			$service->open_dates( 4, '2026-09-14', '2026-09-20' )
		);
	}

	public function test_open_dates_excludes_blacked_out_dates(): void {
		// Only Monday the 14th and 21st fall in range and are enabled; the 14th is blacked out.
		$service = $this->service(
			[ $this->weekday_row( 4, 1, true ) ],
			[ $this->blackout_row( '4', '2026-09-14' ) ]
		);

		$this->assertSame( [ '2026-09-21' ], $service->open_dates( 4, '2026-09-14', '2026-09-21' ) );
	}

	public function test_open_dates_returns_an_empty_list_for_an_invalid_range(): void {
		$service = $this->service();

		$this->assertSame( [], $service->open_dates( 4, '2026-09-20', '2026-09-14' ) );
	}

	public function test_cutoff_deadline_is_null_when_the_schedule_is_null(): void {
		$this->assertNull( $this->service()->cutoff_deadline( null, '2026-09-14' ) );
	}

	public function test_cutoff_deadline_is_null_when_the_day_is_not_open(): void {
		$service  = $this->service( [ $this->weekday_row( 4, 1, false ) ] );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$this->assertNull( $service->cutoff_deadline( $schedule, '2026-09-14' ) );
	}

	public function test_cutoff_deadline_is_the_configured_time_on_the_day_before_fulfilment(): void {
		// 2026-09-18 is a Friday (day_of_week 5); its 1-day cutoff deadline is Thursday.
		$service  = $this->service( [ $this->weekday_row( 4, 5, true ) ], [], 1, '23:30:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$deadline = $service->cutoff_deadline( $schedule, '2026-09-18' );

		$this->assertNotNull( $deadline );
		$this->assertSame( '2026-09-17 23:30:00', $deadline->in_wp_timezone()->to_database() );
	}

	public function test_cutoff_deadline_subtracts_more_than_one_day_when_configured(): void {
		// 2026-09-14 is a Monday (day_of_week 1); 2 cutoff days lands on the Saturday before.
		$service  = $this->service( [ $this->weekday_row( 4, 1, true ) ], [], 2, '09:00:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$deadline = $service->cutoff_deadline( $schedule, '2026-09-14' );

		$this->assertNotNull( $deadline );
		$this->assertSame( '2026-09-12 09:00:00', $deadline->in_wp_timezone()->to_database() );
	}

	public function test_cutoff_deadline_with_zero_cutoff_days_falls_on_the_fulfilment_date_itself(): void {
		$service  = $this->service( [ $this->weekday_row( 4, 1, true ) ], [], 0, '09:00:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$deadline = $service->cutoff_deadline( $schedule, '2026-09-14' );

		$this->assertNotNull( $deadline );
		$this->assertSame( '2026-09-14 09:00:00', $deadline->in_wp_timezone()->to_database() );
	}

	public function test_cutoff_deadline_crosses_a_month_boundary(): void {
		// 2026-10-01 is a Thursday (day_of_week 4); its 1-day cutoff deadline falls in September.
		$service  = $this->service( [ $this->weekday_row( 4, 4, true ) ], [], 1, '23:30:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$deadline = $service->cutoff_deadline( $schedule, '2026-10-01' );

		$this->assertNotNull( $deadline );
		$this->assertSame( '2026-09-30 23:30:00', $deadline->in_wp_timezone()->to_database() );
	}

	public function test_cutoff_deadline_crosses_a_year_boundary(): void {
		// 2027-01-01 is a Friday (day_of_week 5); its 1-day cutoff deadline falls in the prior year.
		$service  = $this->service( [ $this->weekday_row( 4, 5, true ) ], [], 1, '08:00:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$deadline = $service->cutoff_deadline( $schedule, '2027-01-01' );

		$this->assertNotNull( $deadline );
		$this->assertSame( '2026-12-31 08:00:00', $deadline->in_wp_timezone()->to_database() );
	}

	public function test_cutoff_has_not_passed_for_a_date_far_in_the_future(): void {
		$service  = $this->service( [ $this->weekday_row( 4, 1, true ) ], [], 1, '00:00:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		// A Monday far enough out that "now" can never catch up to it in a test run.
		$this->assertFalse( $service->cutoff_has_passed( $schedule, '2099-01-05' ) );
	}

	public function test_cutoff_has_passed_for_a_date_far_in_the_past(): void {
		$service  = $this->service( [ $this->weekday_row( 4, 1, true ) ], [], 1, '00:00:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$this->assertTrue( $service->cutoff_has_passed( $schedule, '2020-01-06' ) );
	}

	public function test_is_available_is_false_when_the_schedule_is_null(): void {
		$this->assertFalse( $this->service()->is_available( null, '2026-09-14' ) );
	}

	public function test_is_available_is_true_for_an_open_unblocked_future_date(): void {
		$service  = $this->service( [ $this->weekday_row( 4, 1, true ) ], [], 1, '00:00:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$this->assertTrue( $service->is_available( $schedule, '2099-01-05' ) );
	}

	public function test_is_available_is_false_once_the_cutoff_has_passed(): void {
		$service  = $this->service( [ $this->weekday_row( 4, 1, true ) ], [], 1, '00:00:00' );
		$schedule = ( new Schedule( 'Test' ) )->set_id( 4 );

		$this->assertFalse( $service->is_available( $schedule, '2020-01-06' ) );
	}

	public function test_eligible_dates_is_empty_when_the_schedule_is_null(): void {
		$this->assertSame( [], $this->service()->eligible_dates( null, '2026-09-14', '2026-09-20' ) );
	}

	public function test_eligible_dates_excludes_dates_whose_cutoff_has_passed(): void {
		$service  = $this->service( [ $this->weekday_row( 4, 1, true ) ], [], 1, '00:00:00' );
		$schedule = ( new Schedule( 'Past' ) )->set_id( 4 );

		// 2020-01-06 is a Monday the schedule is open on, so open_dates() would include
		// it - but its cutoff passed years ago, so eligible_dates() excludes it.
		$this->assertSame( [ '2020-01-06' ], $service->open_dates( 4, '2020-01-06', '2020-01-06' ) );
		$this->assertSame( [], $service->eligible_dates( $schedule, '2020-01-06', '2020-01-06' ) );
	}

	public function test_schedule_for_destination_returns_null_when_nothing_is_assigned(): void {
		$destinations_wpdb = $this->wpdb();
		$destinations_wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

		$this->stub_cutoff( 0, '00:00:00' );

		$service = new Availability_Service(
			new Schedule_Weekday_Repository( $this->wpdb(), $this->clock() ),
			new Blackout_Repository( $this->wpdb(), $this->clock() ),
			new Settings_Store(),
			$this->clock(),
			new Schedule_Destination_Repository( $destinations_wpdb, $this->clock() ),
			new Schedule_Repository( $this->wpdb(), $this->clock() )
		);

		$this->assertNull( $service->schedule_for_destination( 'shipping_zone', '5' ) );
	}

	public function test_schedule_for_destination_returns_the_assigned_schedule(): void {
		$destinations_wpdb = $this->wpdb();
		$destinations_wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'               => '1',
					'schedule_id'      => '4',
					'destination_type' => 'shipping_zone',
					'destination_key'  => '5',
					'date_created'     => '2026-01-01 00:00:00',
					'date_updated'     => '2026-01-01 00:00:00',
				],
			]
		);

		$schedules_wpdb = $this->wpdb();
		$schedules_wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			[
				'id'           => '4',
				'name'         => 'Karachi Delivery',
				'date_created' => '2026-01-01 00:00:00',
				'date_updated' => '2026-01-01 00:00:00',
			]
		);

		$this->stub_cutoff( 0, '00:00:00' );

		$service = new Availability_Service(
			new Schedule_Weekday_Repository( $this->wpdb(), $this->clock() ),
			new Blackout_Repository( $this->wpdb(), $this->clock() ),
			new Settings_Store(),
			$this->clock(),
			new Schedule_Destination_Repository( $destinations_wpdb, $this->clock() ),
			new Schedule_Repository( $schedules_wpdb, $this->clock() )
		);

		$schedule = $service->schedule_for_destination( 'shipping_zone', '5' );

		$this->assertNotNull( $schedule );
		$this->assertSame( 'Karachi Delivery', $schedule->name() );
	}
}
