<?php
/**
 * Unit tests for the blackout service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use FuelChef\Subscriptions\Services\Blackout_Service;
use FuelChef\Subscriptions\Services\Exceptions\Validation_Exception;
use FuelChef\Subscriptions\Tests\Unit\Repositories\Repository_TestCase;

/**
 * @covers \FuelChef\Subscriptions\Services\Blackout_Service
 */
final class Blackout_Service_Test extends Repository_TestCase {


	private function service( ?Blackout_Repository $repository = null ): Blackout_Service {
		return new Blackout_Service( $repository ?? new Blackout_Repository( $this->wpdb(), $this->clock() ) );
	}

	public function test_add_creates_a_global_blackout(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );
		$wpdb->insert_id = 1;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$blackout = $this->service( new Blackout_Repository( $wpdb, $this->clock() ) )
			->add( null, '2026-12-25', 'Christmas Day' );

		$this->assertNull( $blackout->schedule_id() );
		$this->assertSame( '2026-12-25', $blackout->date() );
		$this->assertSame( 'Christmas Day', $blackout->reason() );
	}

	public function test_add_rejects_an_invalid_date(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service()->add( null, 'not-a-date' );
	}

	public function test_add_rejects_a_date_already_blacked_out(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'            => '1',
					'schedule_id'   => null,
					'blackout_date' => '2026-12-25',
					'reason'        => null,
					'date_created'  => '2026-01-01 00:00:00',
					'date_updated'  => '2026-01-01 00:00:00',
				],
			]
		);

		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );

		$this->expectException( Validation_Exception::class );

		$this->service( new Blackout_Repository( $wpdb, $this->clock() ) )->add( null, '2026-12-25' );
	}

	public function test_add_caps_the_reason_at_255_characters(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );
		$wpdb->insert_id = 1;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$blackout = $this->service( new Blackout_Repository( $wpdb, $this->clock() ) )
			->add( null, '2026-12-25', str_repeat( 'x', 300 ) );

		$this->assertSame( 255, strlen( (string) $blackout->reason() ) );
	}

	public function test_update_reason_updates_an_existing_blackout(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			[
				'id'            => '1',
				'schedule_id'   => null,
				'blackout_date' => '2026-12-25',
				'reason'        => 'Old reason',
				'date_created'  => '2026-01-01 00:00:00',
				'date_updated'  => '2026-01-01 00:00:00',
			]
		);
		$wpdb->shouldReceive( 'update' )->once()->with(
			'wp_fcs_blackouts',
			\Mockery::on( static fn ( array $data ): bool => 'New reason' === $data['reason'] ),
			[ 'id' => 1 ]
		)->andReturn( 1 );

		$blackout = $this->service( new Blackout_Repository( $wpdb, $this->clock() ) )
			->update_reason( 1, 'New reason' );

		$this->assertSame( 'New reason', $blackout->reason() );
	}

	public function test_remove_deletes_the_blackout(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'delete' )->once()->with( 'wp_fcs_blackouts', [ 'id' => 1 ], [ '%d' ] )->andReturn( 1 );

		$this->service( new Blackout_Repository( $wpdb, $this->clock() ) )->remove( 1 );
	}
}
