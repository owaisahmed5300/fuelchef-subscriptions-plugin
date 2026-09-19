<?php
/**
 * Unit tests for the settings store.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Services\Settings_Service;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use Mockery;

/**
 * @covers \FuelChef\Subscriptions\Services\Settings_Service
 */
final class Settings_Service_Test extends TestCase {


	protected function setUp(): void {
		parent::setUp();

		// The default fulfilment-date and subscribe-and-save labels are translated at read
		// time, so every get() call reaches __() even when nothing else is stored.
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( '__' )->returnArg( 1 );
	}

	public function test_get_returns_defaults_when_no_option_is_stored(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 1, $settings->cutoff_days() );
		$this->assertSame( '17:00:00', $settings->cutoff_time() );
		$this->assertSame( 5, $settings->subscribe_discount_percent() );
		$this->assertSame( Subscribe_Applicability::INITIAL_AND_RENEWALS, $settings->subscribe_applicability() );
		$this->assertSame( 60, $settings->max_fulfilment_window_days() );
		$this->assertSame( 'Delivery date', $settings->delivery_date_label() );
		$this->assertSame( 'Pickup date', $settings->pickup_date_label() );
		$this->assertSame( '', $settings->fulfilment_date_description() );
		$this->assertSame( 'Subscribe for {percent}% off every order', $settings->subscribe_save_label() );
		$this->assertSame( '', $settings->subscribe_save_description() );
		$this->assertSame( 0.0, $settings->minimum_order_amount() );
		$this->assertSame( 0, $settings->minimum_cart_quantity() );
		$this->assertSame( '', $settings->ineligible_message() );
		$this->assertSame( '', $settings->logged_out_message() );
	}

	public function test_get_returns_the_stored_values_when_they_are_valid(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'cutoff_days'                 => 2,
				'cutoff_time'                 => '23:30:00',
				'subscribe_discount_percent'  => 10,
				'subscribe_applicability'     => Subscribe_Applicability::RENEWAL_ONLY,
				'max_fulfilment_window_days'  => 30,
				'delivery_date_label'         => 'Preferred delivery day',
				'pickup_date_label'           => 'Preferred pickup day',
				'fulfilment_date_description' => 'Choose any day we can fulfil in your area.',
				'subscribe_save_label'        => 'Save {percent}% every order',
				'subscribe_save_description'  => 'Cancel anytime from My Account.',
				'minimum_order_amount'        => 50.0,
				'minimum_cart_quantity'       => 3,
				'ineligible_message'          => 'Add more to unlock this.',
				'logged_out_message'          => 'Log in to unlock this.',
				'fulfilment_window_message'   => 'Open between {start} and {end}.',
			]
		);

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 2, $settings->cutoff_days() );
		$this->assertSame( '23:30:00', $settings->cutoff_time() );
		$this->assertSame( 10, $settings->subscribe_discount_percent() );
		$this->assertSame( Subscribe_Applicability::RENEWAL_ONLY, $settings->subscribe_applicability() );
		$this->assertSame( 30, $settings->max_fulfilment_window_days() );
		$this->assertSame( 'Preferred delivery day', $settings->delivery_date_label() );
		$this->assertSame( 'Preferred pickup day', $settings->pickup_date_label() );
		$this->assertSame( 'Choose any day we can fulfil in your area.', $settings->fulfilment_date_description() );
		$this->assertSame( 'Save {percent}% every order', $settings->subscribe_save_label() );
		$this->assertSame( 'Cancel anytime from My Account.', $settings->subscribe_save_description() );
		$this->assertSame( 50.0, $settings->minimum_order_amount() );
		$this->assertSame( 3, $settings->minimum_cart_quantity() );
		$this->assertSame( 'Add more to unlock this.', $settings->ineligible_message() );
		$this->assertSame( 'Log in to unlock this.', $settings->logged_out_message() );
		$this->assertSame( 'Open between {start} and {end}.', $settings->fulfilment_window_message() );
	}

	public function test_get_falls_back_to_the_default_minimum_order_amount_when_the_stored_one_is_negative(): void {
		Functions\when( 'get_option' )->justReturn( [ 'minimum_order_amount' => -10.0 ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 0.0, $settings->minimum_order_amount() );
	}

	public function test_get_falls_back_to_the_default_minimum_cart_quantity_when_the_stored_one_is_negative(): void {
		Functions\when( 'get_option' )->justReturn( [ 'minimum_cart_quantity' => -1 ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 0, $settings->minimum_cart_quantity() );
	}

	public function test_get_falls_back_to_the_default_cutoff_time_when_the_stored_one_is_not_a_valid_time(): void {
		Functions\when( 'get_option' )->justReturn( [ 'cutoff_time' => 'not-a-time' ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( '17:00:00', $settings->cutoff_time() );
	}

	public function test_get_falls_back_to_the_default_applicability_when_the_stored_one_is_unknown(): void {
		Functions\when( 'get_option' )->justReturn( [ 'subscribe_applicability' => 'every_third_order' ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( Subscribe_Applicability::INITIAL_AND_RENEWALS, $settings->subscribe_applicability() );
	}

	public function test_get_falls_back_to_the_default_discount_when_the_stored_one_is_out_of_range(): void {
		Functions\when( 'get_option' )->justReturn( [ 'subscribe_discount_percent' => 150 ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 5, $settings->subscribe_discount_percent() );
	}

	public function test_get_falls_back_to_the_default_cutoff_days_when_the_stored_one_is_negative(): void {
		Functions\when( 'get_option' )->justReturn( [ 'cutoff_days' => -1 ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 1, $settings->cutoff_days() );
	}

	public function test_get_falls_back_to_the_default_max_fulfilment_window_when_the_stored_one_is_not_positive(): void {
		Functions\when( 'get_option' )->justReturn( [ 'max_fulfilment_window_days' => 0 ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 60, $settings->max_fulfilment_window_days() );
	}

	public function test_get_falls_back_to_the_default_max_fulfilment_window_when_the_stored_one_is_beyond_the_ceiling(): void {
		Functions\when( 'get_option' )->justReturn(
			[ 'max_fulfilment_window_days' => Settings::MAX_FULFILMENT_WINDOW_DAYS + 1 ]
		);

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 60, $settings->max_fulfilment_window_days() );
	}

	public function test_get_accepts_a_stored_max_fulfilment_window_exactly_at_the_ceiling(): void {
		Functions\when( 'get_option' )->justReturn(
			[ 'max_fulfilment_window_days' => Settings::MAX_FULFILMENT_WINDOW_DAYS ]
		);

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( Settings::MAX_FULFILMENT_WINDOW_DAYS, $settings->max_fulfilment_window_days() );
	}

	public function test_get_falls_back_to_the_default_discount_when_the_stored_one_is_negative(): void {
		Functions\when( 'get_option' )->justReturn( [ 'subscribe_discount_percent' => -1 ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 5, $settings->subscribe_discount_percent() );
	}

	public function test_get_accepts_a_stored_discount_of_exactly_100(): void {
		Functions\when( 'get_option' )->justReturn( [ 'subscribe_discount_percent' => 100 ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 100, $settings->subscribe_discount_percent() );
	}

	public function test_get_accepts_a_stored_discount_of_exactly_zero(): void {
		Functions\when( 'get_option' )->justReturn( [ 'subscribe_discount_percent' => 0 ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 0, $settings->subscribe_discount_percent() );
	}

	public function test_get_falls_back_to_the_default_delivery_date_label_when_the_stored_one_is_blank(): void {
		Functions\when( 'get_option' )->justReturn( [ 'delivery_date_label' => '   ' ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 'Delivery date', $settings->delivery_date_label() );
	}

	public function test_get_falls_back_to_the_default_delivery_date_label_when_the_stored_one_is_too_long(): void {
		Functions\when( 'get_option' )->justReturn( [ 'delivery_date_label' => str_repeat( 'a', 191 ) ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 'Delivery date', $settings->delivery_date_label() );
	}

	public function test_get_falls_back_to_the_default_pickup_date_label_when_the_stored_one_is_blank(): void {
		Functions\when( 'get_option' )->justReturn( [ 'pickup_date_label' => '   ' ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 'Pickup date', $settings->pickup_date_label() );
	}

	public function test_get_falls_back_to_the_default_pickup_date_label_when_the_stored_one_is_too_long(): void {
		Functions\when( 'get_option' )->justReturn( [ 'pickup_date_label' => str_repeat( 'a', 191 ) ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( 'Pickup date', $settings->pickup_date_label() );
	}

	public function test_get_keeps_an_empty_stored_fulfilment_date_description(): void {
		Functions\when( 'get_option' )->justReturn( [ 'fulfilment_date_description' => '' ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( '', $settings->fulfilment_date_description() );
	}

	public function test_get_falls_back_to_an_empty_fulfilment_date_description_when_the_stored_one_is_too_long(): void {
		Functions\when( 'get_option' )->justReturn( [ 'fulfilment_date_description' => str_repeat( 'a', 301 ) ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( '', $settings->fulfilment_date_description() );
	}

	public function test_get_falls_back_to_an_empty_logged_out_message_when_the_stored_one_is_too_long(): void {
		Functions\when( 'get_option' )->justReturn( [ 'logged_out_message' => str_repeat( 'a', 301 ) ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( '', $settings->logged_out_message() );
	}

	public function test_get_falls_back_to_an_empty_fulfilment_window_message_when_the_stored_one_is_too_long(): void {
		Functions\when( 'get_option' )->justReturn( [ 'fulfilment_window_message' => str_repeat( 'a', 301 ) ] );

		$settings = ( new Settings_Service() )->get();

		$this->assertSame( '', $settings->fulfilment_window_message() );
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
					'delivery_date_label'         => 'Preferred delivery day',
					'pickup_date_label'           => 'Preferred pickup day',
					'fulfilment_date_description' => 'Choose any day we can fulfil in your area.',
					'subscribe_save_label'        => 'Save {percent}% every order',
					'subscribe_save_description'  => 'Cancel anytime from My Account.',
					'minimum_order_amount'        => 50.0,
					'minimum_cart_quantity'       => 3,
					'ineligible_message'          => 'Add more to unlock this.',
					'logged_out_message'          => 'Log in to unlock this.',
					'fulfilment_window_message'   => 'Open between {start} and {end}.',
				]
			);

		( new Settings_Service() )->save(
			new Settings(
				2,
				'23:30:00',
				10,
				Subscribe_Applicability::RENEWAL_ONLY,
				30,
				'Preferred delivery day',
				'Preferred pickup day',
				'Choose any day we can fulfil in your area.',
				'Save {percent}% every order',
				'Cancel anytime from My Account.',
				minimum_order_amount: 50.0,
				minimum_cart_quantity: 3,
				ineligible_message: 'Add more to unlock this.',
				logged_out_message: 'Log in to unlock this.',
				fulfilment_window_message: 'Open between {start} and {end}.'
			)
		);
	}

	public function test_save_fires_an_updated_action_with_the_settings(): void {
		Functions\when( 'update_option' )->justReturn( true );

		Actions\expectDone( 'fuelchef_subscriptions/settings/updated' )
			->once()
			->with( Mockery::type( Settings::class ) );

		( new Settings_Service() )->save(
			new Settings( 1, '17:00:00', 5, Subscribe_Applicability::INITIAL_AND_RENEWALS, 60, 'Delivery date', 'Pickup date', '', 'Subscribe & Save {percent}%', '' )
		);
	}
}
