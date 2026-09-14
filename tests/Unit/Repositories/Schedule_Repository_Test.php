<?php
/**
 * Unit tests for the schedule repository.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Repositories;

use Brain\Monkey\Actions;
use FuelChef\Subscriptions\Entities\Schedule;
use FuelChef\Subscriptions\Repositories\Exceptions\Entity_Not_Found_Exception;
use FuelChef\Subscriptions\Repositories\Schedule_Repository;
use InvalidArgumentException;
use Mockery;

/**
 * Also exercises Abstract_Repository's find/insert/update/delete, which every
 * other repository shares.
 *
 * @covers \FuelChef\Subscriptions\Repositories\Schedule_Repository
 * @covers \FuelChef\Subscriptions\Repositories\Abstracts\Abstract_Repository
 */
final class Schedule_Repository_Test extends Repository_TestCase {


	public function test_find_returns_null_when_no_row_exists(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );

		$this->assertNull( $repository->find( 1 ) );
	}

	public function test_find_hydrates_a_row_into_a_schedule(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			[
				'id'           => '5',
				'name'         => 'Karachi Delivery',
				'date_created' => '2026-01-01 00:00:00',
				'date_updated' => '2026-01-02 00:00:00',
			]
		);

		$repository = new Schedule_Repository( $wpdb, $this->clock() );
		$schedule   = $repository->find( 5 );

		$this->assertInstanceOf( Schedule::class, $schedule );
		$this->assertSame( 5, $schedule->id() );
		$this->assertSame( 'Karachi Delivery', $schedule->name() );
		$this->assertSame( '2026-01-01 00:00:00', $schedule->date_created()?->to_database() );
	}

	public function test_find_does_not_query_twice_for_the_same_id(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			[
				'id'           => '5',
				'name'         => 'Karachi Delivery',
				'date_created' => '2026-01-01 00:00:00',
				'date_updated' => '2026-01-01 00:00:00',
			]
		);

		$repository = new Schedule_Repository( $wpdb, $this->clock() );

		$repository->find( 5 );
		$repository->find( 5 );
	}

	public function test_find_or_fail_throws_when_the_row_does_not_exist(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );

		$this->expectException( Entity_Not_Found_Exception::class );

		$repository->find_or_fail( 9 );
	}

	public function test_insert_sets_the_id_and_timestamps(): void {
		$wpdb            = $this->wpdb();
		$wpdb->insert_id = 7;
		$wpdb->shouldReceive( 'insert' )->once()->with(
			'wp_fcs_schedules',
			Mockery::on(
				static fn ( array $data ): bool => 'Main Store Pickup' === $data['name']
					&& is_string( $data['date_created'] )
					&& is_string( $data['date_updated'] )
			)
		)->andReturn( 1 );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );
		$schedule   = $repository->insert( new Schedule( 'Main Store Pickup' ) );

		$this->assertSame( 7, $schedule->id() );
		$this->assertNotNull( $schedule->date_created() );
		$this->assertNotNull( $schedule->date_updated() );
	}

	public function test_insert_populates_the_cache(): void {
		$wpdb            = $this->wpdb();
		$wpdb->insert_id = 3;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );
		$wpdb->shouldNotReceive( 'get_row' );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );
		$inserted   = $repository->insert( new Schedule( 'Karachi Delivery' ) );

		$this->assertSame( $inserted, $repository->find( 3 ) );
	}

	public function test_update_writes_the_current_state(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'update' )->once()->with(
			'wp_fcs_schedules',
			Mockery::on(
				static fn ( array $data ): bool => 'Renamed' === $data['name'] && is_string( $data['date_updated'] )
			),
			[ 'id' => 4 ]
		)->andReturn( 1 );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );
		$schedule   = ( new Schedule( 'Renamed' ) )->set_id( 4 );

		$repository->update( $schedule );
	}

	public function test_update_rejects_an_entity_that_has_not_been_persisted(): void {
		$repository = new Schedule_Repository( $this->wpdb(), $this->clock() );

		$this->expectException( InvalidArgumentException::class );

		$repository->update( new Schedule( 'Unsaved' ) );
	}

	public function test_delete_removes_the_row(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'delete' )->once()->with( 'wp_fcs_schedules', [ 'id' => 6 ], [ '%d' ] )->andReturn( 1 );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );

		$repository->delete( 6 );
	}

	public function test_all_caches_the_full_list(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn(
			[
				[
					'id'           => '1',
					'name'         => 'A',
					'date_created' => '2026-01-01 00:00:00',
					'date_updated' => '2026-01-01 00:00:00',
				],
			]
		);

		$repository = new Schedule_Repository( $wpdb, $this->clock() );

		$first  = $repository->all();
		$second = $repository->all();

		$this->assertSame( $first, $second );
	}

	public function test_insert_fires_a_created_action_with_the_entity(): void {
		$wpdb            = $this->wpdb();
		$wpdb->insert_id = 7;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		Actions\expectDone( 'fuelchef_subscriptions/schedules/created' )
			->once()
			->with( Mockery::type( Schedule::class ) );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );
		$repository->insert( new Schedule( 'Main Store Pickup' ) );
	}

	public function test_update_fires_an_updated_action_with_the_entity(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		Actions\expectDone( 'fuelchef_subscriptions/schedules/updated' )
			->once()
			->with( Mockery::type( Schedule::class ) );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );
		$repository->update( ( new Schedule( 'Renamed' ) )->set_id( 4 ) );
	}

	public function test_delete_fires_a_deleted_action_with_the_id_and_entity(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			[
				'id'           => '6',
				'name'         => 'To Delete',
				'date_created' => '2026-01-01 00:00:00',
				'date_updated' => '2026-01-01 00:00:00',
			]
		);
		$wpdb->shouldReceive( 'delete' )->once()->andReturn( 1 );

		Actions\expectDone( 'fuelchef_subscriptions/schedules/deleted' )
			->once()
			->with( 6, Mockery::type( Schedule::class ) );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );
		$repository->delete( 6 );
	}

	public function test_delete_does_not_fire_a_deleted_action_when_the_row_did_not_exist(): void {
		$wpdb = $this->wpdb();
		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'delete' )->once()->andReturn( 0 );

		Actions\expectDone( 'fuelchef_subscriptions/schedules/deleted' )->never();

		$repository = new Schedule_Repository( $wpdb, $this->clock() );
		$repository->delete( 999 );
	}

	public function test_inserting_a_schedule_invalidates_the_cached_list(): void {
		$wpdb = $this->wpdb();
		// Once for the cache-priming call, once more after insert() invalidates it.
		$wpdb->shouldReceive( 'get_results' )->twice()->andReturn( [] );
		$wpdb->insert_id = 9;
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		$repository = new Schedule_Repository( $wpdb, $this->clock() );

		$repository->all();
		$repository->insert( new Schedule( 'New Schedule' ) );
		$repository->all();
	}
}
