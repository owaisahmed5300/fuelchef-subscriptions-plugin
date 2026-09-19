<?php
/**
 * Unit tests for the blackout repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Repositories;

use Brain\Monkey\Filters;
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

	public function test_find_by_schedule_hits_the_database_only_once_across_repeated_calls(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[ $this->row( '4', '2026-09-14' ) ]
		);

		$repository = new Blackout_Repository( $wpdb, $this->clock() );

		$first  = $repository->find_by_schedule( 4 );
		$second = $repository->find_by_schedule( 4 );

		$this->assertSame( $first, $second );
	}

	public function test_updating_a_blackout_invalidates_its_schedules_cached_list(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn(
			[ $this->row( '4', '2026-09-14' ) ],
			[ $this->row( '4', '2026-09-14', 'Updated reason' ) ]
		);
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$repository = new Blackout_Repository( $wpdb, $this->clock() );

		$before = $repository->find_by_schedule( 4 );

		$blackout = new Blackout( 4, '2026-09-14', 'Updated reason' );
		$blackout->set_id( 1 );
		$repository->update( $blackout );

		$after = $repository->find_by_schedule( 4 );

		$this->assertNull( $before[0]->reason() );
		$this->assertSame( 'Updated reason', $after[0]->reason() );
	}

	public function test_find_by_schedule_between_scopes_to_the_date_range_and_schedule(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->with(
			Mockery::on(
				static fn ( string $sql ): bool => str_contains( $sql, 'schedule_id = 4' )
					&& str_contains( $sql, 'BETWEEN 2026-12-01 AND 2026-12-31' )
			),
			Mockery::any()
		)->andReturn( [ $this->row( '4', '2026-12-25' ) ] );

		$repository = new Blackout_Repository( $wpdb, $this->clock() );
		$blackouts  = $repository->find_by_schedule_between( 4, '2026-12-01', '2026-12-31' );

		$this->assertCount( 1, $blackouts );
		$this->assertSame( '2026-12-25', $blackouts[0]->date() );
	}

	public function test_find_by_schedule_between_scopes_to_global_blackouts_when_schedule_is_null(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->with(
			Mockery::on( static fn ( string $sql ): bool => str_contains( $sql, 'schedule_id IS NULL' ) ),
			Mockery::any()
		)->andReturn( [ $this->row( null, '2026-12-25' ) ] );

		$repository = new Blackout_Repository( $wpdb, $this->clock() );
		$repository->find_by_schedule_between( null, '2026-12-01', '2026-12-31' );
	}

	public function test_find_by_schedule_between_is_not_cached(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( [] );

		$repository = new Blackout_Repository( $wpdb, $this->clock() );

		$repository->find_by_schedule_between( 4, '2026-12-01', '2026-12-31' );
		$repository->find_by_schedule_between( 4, '2026-12-01', '2026-12-31' );
	}

	public function test_find_by_schedule_between_result_is_filterable(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[ $this->row( null, '2026-12-25' ) ]
		);

		Filters\expectApplied( 'fuelchef_subscriptions/blackouts/between' )
			->once()
			->andReturnUsing(
				static function ( array $blackouts, ?int $schedule_id, string $from, string $to ): array {
					self::assertNull( $schedule_id );
					self::assertSame( '2026-12-01', $from );
					self::assertSame( '2026-12-31', $to );

					$blackouts[] = new Blackout( null, '2026-12-31', 'Injected by filter' );

					return $blackouts;
				}
			);

		$repository = new Blackout_Repository( $wpdb, $this->clock() );
		$result     = $repository->find_by_schedule_between( null, '2026-12-01', '2026-12-31' );

		$this->assertCount( 2, $result );
		$this->assertSame( 'Injected by filter', $result[1]->reason() );
	}

	public function test_find_by_schedule_between_drops_anything_the_filter_returns_that_is_not_a_blackout(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

		Filters\expectApplied( 'fuelchef_subscriptions/blackouts/between' )
			->once()
			->andReturnUsing(
				static function ( array $blackouts ): array {
					$blackouts[] = 'not a blackout';

					return $blackouts;
				}
			);

		$repository = new Blackout_Repository( $wpdb, $this->clock() );
		$result     = $repository->find_by_schedule_between( null, '2026-12-01', '2026-12-31' );

		$this->assertSame( [], $result );
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
