<?php
/**
 * Unit tests for the settings store.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Settings;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Settings\Settings;
use FuelChef\Subscriptions\Settings\Settings_Store;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Values\Cutoff_Unit;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use Mockery;

/**
 * @covers \FuelChef\Subscriptions\Settings\Settings_Store
 */
final class Settings_Store_Test extends TestCase {


	public function test_get_returns_defaults_when_no_option_is_stored(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 24, $settings->cutoff_amount() );
		$this->assertSame( Cutoff_Unit::HOURS, $settings->cutoff_unit() );
		$this->assertSame( 5, $settings->subscribe_discount_percent() );
		$this->assertSame( Subscribe_Applicability::INITIAL_AND_RENEWALS, $settings->subscribe_applicability() );
	}

	public function test_get_returns_the_stored_values_when_they_are_valid(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'cutoff_amount'              => 48,
				'cutoff_unit'                => Cutoff_Unit::DAYS,
				'subscribe_discount_percent' => 10,
				'subscribe_applicability'    => Subscribe_Applicability::RENEWAL_ONLY,
			]
		);

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 48, $settings->cutoff_amount() );
		$this->assertSame( Cutoff_Unit::DAYS, $settings->cutoff_unit() );
		$this->assertSame( 10, $settings->subscribe_discount_percent() );
		$this->assertSame( Subscribe_Applicability::RENEWAL_ONLY, $settings->subscribe_applicability() );
	}

	public function test_get_falls_back_to_the_default_cutoff_unit_when_the_stored_one_is_unknown(): void {
		Functions\when( 'get_option' )->justReturn( [ 'cutoff_unit' => 'fortnights' ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( Cutoff_Unit::HOURS, $settings->cutoff_unit() );
	}

	public function test_get_falls_back_to_the_default_applicability_when_the_stored_one_is_unknown(): void {
		Functions\when( 'get_option' )->justReturn( [ 'subscribe_applicability' => 'every_third_order' ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( Subscribe_Applicability::INITIAL_AND_RENEWALS, $settings->subscribe_applicability() );
	}

	public function test_get_falls_back_to_the_default_discount_when_the_stored_one_is_out_of_range(): void {
		Functions\when( 'get_option' )->justReturn( [ 'subscribe_discount_percent' => 150 ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 5, $settings->subscribe_discount_percent() );
	}

	public function test_get_falls_back_to_the_default_cutoff_amount_when_the_stored_one_is_negative(): void {
		Functions\when( 'get_option' )->justReturn( [ 'cutoff_amount' => -1 ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 24, $settings->cutoff_amount() );
	}

	public function test_save_persists_every_field_under_one_option(): void {
		Functions\expect( 'update_option' )
			->once()
			->with(
				'fuelchef_subscriptions_settings',
				[
					'cutoff_amount'              => 48,
					'cutoff_unit'                => Cutoff_Unit::DAYS,
					'subscribe_discount_percent' => 10,
					'subscribe_applicability'    => Subscribe_Applicability::RENEWAL_ONLY,
				]
			);

		( new Settings_Store() )->save(
			new Settings( 48, Cutoff_Unit::DAYS, 10, Subscribe_Applicability::RENEWAL_ONLY )
		);
	}

	public function test_save_fires_an_updated_action_with_the_settings(): void {
		Functions\when( 'update_option' )->justReturn( true );

		Actions\expectDone( 'fuelchef_subscriptions/settings/updated' )
			->once()
			->with( Mockery::type( Settings::class ) );

		( new Settings_Store() )->save(
			new Settings( 24, Cutoff_Unit::HOURS, 5, Subscribe_Applicability::INITIAL_AND_RENEWALS )
		);
	}
}
