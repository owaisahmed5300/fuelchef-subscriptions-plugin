<?php
/**
 * Unit tests for the settings store.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use Mockery;

/**
 * @covers \FuelChef\Subscriptions\Services\Settings_Store
 */
final class Settings_Store_Test extends TestCase {


	protected function setUp(): void {
		parent::setUp();

		// The default fulfilment-date and subscribe-and-save labels are translated at read
		// time, so every get() call reaches esc_html__() even when nothing else is stored.
		Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	public function test_get_returns_defaults_when_no_option_is_stored(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 1, $settings->cutoff_days() );
		$this->assertSame( '17:00:00', $settings->cutoff_time() );
		$this->assertSame( 5, $settings->subscribe_discount_percent() );
		$this->assertSame( Subscribe_Applicability::INITIAL_AND_RENEWALS, $settings->subscribe_applicability() );
		$this->assertSame( 60, $settings->max_fulfilment_window_days() );
		$this->assertSame( 'Fulfilment date', $settings->fulfilment_date_label() );
		$this->assertSame( '', $settings->fulfilment_date_description() );
		$this->assertSame( 'Subscribe & Save {percent}%', $settings->subscribe_save_label() );
		$this->assertSame( '', $settings->subscribe_save_description() );
	}

	public function test_get_returns_the_stored_values_when_they_are_valid(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'cutoff_days'                 => 2,
				'cutoff_time'                 => '23:30:00',
				'subscribe_discount_percent'  => 10,
				'subscribe_applicability'     => Subscribe_Applicability::RENEWAL_ONLY,
				'max_fulfilment_window_days'  => 30,
				'fulfilment_date_label'       => 'Preferred fulfilment day',
				'fulfilment_date_description' => 'Choose any day we can fulfil in your area.',
				'subscribe_save_label'        => 'Save {percent}% every order',
				'subscribe_save_description'  => 'Cancel anytime from My Account.',
			]
		);

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 2, $settings->cutoff_days() );
		$this->assertSame( '23:30:00', $settings->cutoff_time() );
		$this->assertSame( 10, $settings->subscribe_discount_percent() );
		$this->assertSame( Subscribe_Applicability::RENEWAL_ONLY, $settings->subscribe_applicability() );
		$this->assertSame( 30, $settings->max_fulfilment_window_days() );
		$this->assertSame( 'Preferred fulfilment day', $settings->fulfilment_date_label() );
		$this->assertSame( 'Choose any day we can fulfil in your area.', $settings->fulfilment_date_description() );
		$this->assertSame( 'Save {percent}% every order', $settings->subscribe_save_label() );
		$this->assertSame( 'Cancel anytime from My Account.', $settings->subscribe_save_description() );
	}

	public function test_get_falls_back_to_the_default_cutoff_time_when_the_stored_one_is_not_a_valid_time(): void {
		Functions\when( 'get_option' )->justReturn( [ 'cutoff_time' => 'not-a-time' ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( '17:00:00', $settings->cutoff_time() );
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

	public function test_get_falls_back_to_the_default_cutoff_days_when_the_stored_one_is_negative(): void {
		Functions\when( 'get_option' )->justReturn( [ 'cutoff_days' => -1 ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 1, $settings->cutoff_days() );
	}

	public function test_get_falls_back_to_the_default_max_fulfilment_window_when_the_stored_one_is_not_positive(): void {
		Functions\when( 'get_option' )->justReturn( [ 'max_fulfilment_window_days' => 0 ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 60, $settings->max_fulfilment_window_days() );
	}

	public function test_get_falls_back_to_the_default_fulfilment_date_label_when_the_stored_one_is_blank(): void {
		Functions\when( 'get_option' )->justReturn( [ 'fulfilment_date_label' => '   ' ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 'Fulfilment date', $settings->fulfilment_date_label() );
	}

	public function test_get_falls_back_to_the_default_fulfilment_date_label_when_the_stored_one_is_too_long(): void {
		Functions\when( 'get_option' )->justReturn( [ 'fulfilment_date_label' => str_repeat( 'a', 191 ) ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( 'Fulfilment date', $settings->fulfilment_date_label() );
	}

	public function test_get_keeps_an_empty_stored_fulfilment_date_description(): void {
		Functions\when( 'get_option' )->justReturn( [ 'fulfilment_date_description' => '' ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( '', $settings->fulfilment_date_description() );
	}

	public function test_get_falls_back_to_an_empty_fulfilment_date_description_when_the_stored_one_is_too_long(): void {
		Functions\when( 'get_option' )->justReturn( [ 'fulfilment_date_description' => str_repeat( 'a', 301 ) ] );

		$settings = ( new Settings_Store() )->get();

		$this->assertSame( '', $settings->fulfilment_date_description() );
	}

	public function test_save_persists_every_field_under_one_option(): void {
		Functions\expect( 'update_option' )
			->once()
			->with(
				'fuelchef_subscriptions_settings',
				[
					'cutoff_days'                 => 2,
					'cutoff_time'                 => '23:30:00',
					'subscribe_discount_percent'  => 10,
					'subscribe_applicability'     => Subscribe_Applicability::RENEWAL_ONLY,
					'max_fulfilment_window_days'  => 30,
					'fulfilment_date_label'       => 'Preferred fulfilment day',
					'fulfilment_date_description' => 'Choose any day we can fulfil in your area.',
					'subscribe_save_label'        => 'Save {percent}% every order',
					'subscribe_save_description'  => 'Cancel anytime from My Account.',
				]
			);

		( new Settings_Store() )->save(
			new Settings(
				2,
				'23:30:00',
				10,
				Subscribe_Applicability::RENEWAL_ONLY,
				30,
				'Preferred fulfilment day',
				'Choose any day we can fulfil in your area.',
				'Save {percent}% every order',
				'Cancel anytime from My Account.'
			)
		);
	}

	public function test_save_fires_an_updated_action_with_the_settings(): void {
		Functions\when( 'update_option' )->justReturn( true );

		Actions\expectDone( 'fuelchef_subscriptions/settings/updated' )
			->once()
			->with( Mockery::type( Settings::class ) );

		( new Settings_Store() )->save(
			new Settings( 1, '17:00:00', 5, Subscribe_Applicability::INITIAL_AND_RENEWALS, 60, 'Fulfilment date', '', 'Subscribe & Save {percent}%', '' )
		);
	}
}
