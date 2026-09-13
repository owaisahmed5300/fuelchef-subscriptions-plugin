<?php
/**
 * Unit tests for the blackout repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Repositories;

use FuelChef\Subscriptions\Entities\Blackout;
use FuelChef\Subscriptions\Repositories\Blackout_Repository;
use Mockery;

/**
 * @covers \FuelChef\Subscriptions\Repositories\Blackout_Repository
 */
final class Blackout_Repository_Test extends Repository_TestCase {


	/**
	 * @return array<string, mixed>
	 */
	private function row( ?string $schedule_id, string $date, ?string $reason = null ): array {
		return [
			'id'            => '1',
			'schedule_id'   => $schedule_id,
			'blackout_date' => $date,
			'reason'        => $reason,
			'date_created'  => '2026-01-01 00:00:00',
			'date_updated'  => '2026-01-01 00:00:00',
		];
	}

	public function test_find_by_schedule_null_returns_only_global_blackouts(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[ $this->row( null, '2026-12-25' ) ]
		);

		$repository = new Blackout_Repository( $wpdb, $this->clock() );
		$blackouts  = $repository->find_by_schedule( null );

		$this->assertCount( 1, $blackouts );
		$this->assertNull( $blackouts[0]->schedule_id() );
		$this->assertSame( '2026-12-25', $blackouts[0]->date() );
	}

	public function test_the_global_list_is_cached_separately_from_any_schedule(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( [] );

		$repository = new Blackout_Repository( $wpdb, $this->clock() );

		$repository->find_by_schedule( null );
		$repository->find_by_schedule( 4 );
	}

	public function test_exists_on_date_is_true_for_a_matching_date(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[ $this->row( '4', '2026-09-14', 'Local Maintenance' ) ]
		);

		$repository = new Blackout_Repository( $wpdb, $this->clock() );

		$this->assertTrue( $repository->exists_on_date( 4, '2026-09-14' ) );
		$this->assertFalse( $repository->exists_on_date( 4, '2026-09-15' ) );
	}

	public function test_delete_by_schedule_never_touches_global_blackouts(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->with(
			Mockery::on( static fn ( string $sql ): bool => ! str_contains( $sql, 'IS NULL' ) ),
			Mockery::any()
		)->andReturn( [ $this->row( '4', '2026-09-14' ) ] );
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'delete' )->once()->andReturn( 1 );

		$repository = new Blackout_Repository( $wpdb, $this->clock() );

		$repository->delete_by_schedule( 4 );
	}

	public function test_reason_round_trips_as_null_when_not_given(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $this->row( null, '2026-12-25', null ) );

		$repository = new Blackout_Repository( $wpdb, $this->clock() );
		$blackout   = $repository->find( 1 );

		$this->assertNull( $blackout?->reason() );
	}

	public function test_insert_writes_a_null_schedule_id_for_a_global_blackout(): void {
		$wpdb            = $this->wpdb();
		$wpdb->insert_id = 1;
		$wpdb->shouldReceive( 'insert' )->once()->with(
			'wp_fcs_blackouts',
			Mockery::on( static fn ( array $data ): bool => null === $data['schedule_id'] )
		)->andReturn( 1 );

		$repository = new Blackout_Repository( $wpdb, $this->clock() );

		$repository->insert( new Blackout( null, '2026-12-25', 'Christmas Day' ) );
	}
}
