<?php
/**
 * Unit tests for the subscribe-and-save eligibility service.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Services\Subscribe_Eligibility_Service;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;

/**
 * @covers \FuelChef\Subscriptions\Services\Subscribe_Eligibility_Service
 */
final class Subscribe_Eligibility_Service_Test extends TestCase {


	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( '__' )->returnArg( 1 );
	}

	private function settings( float $minimum_order_amount, int $minimum_cart_quantity ): Settings {
		return new Settings(
			1,
			'17:00:00',
			5,
			Subscribe_Applicability::INITIAL_AND_RENEWALS,
			60,
			'Fulfilment date',
			'',
			'Subscribe & Save {percent}%',
			'',
			minimum_order_amount: $minimum_order_amount,
			minimum_cart_quantity: $minimum_cart_quantity
		);
	}

	private function subject(): Subscribe_Eligibility_Service {
		return new Subscribe_Eligibility_Service();
	}

	public function test_eligible_when_both_minimums_are_zero(): void {
		$settings = $this->settings( 0.0, 0 );

		$this->assertTrue( $this->subject()->is_eligible( 0.0, 0, $settings ) );
	}

	public function test_ineligible_when_the_subtotal_is_below_the_minimum_order_amount(): void {
		$settings = $this->settings( 50.0, 0 );

		$this->assertFalse( $this->subject()->is_eligible( 49.99, 10, $settings ) );
	}

	public function test_eligible_when_the_subtotal_meets_the_minimum_order_amount(): void {
		$settings = $this->settings( 50.0, 0 );

		$this->assertTrue( $this->subject()->is_eligible( 50.0, 1, $settings ) );
	}

	public function test_ineligible_when_the_quantity_is_below_the_minimum_cart_quantity(): void {
		$settings = $this->settings( 0.0, 3 );

		$this->assertFalse( $this->subject()->is_eligible( 500.0, 2, $settings ) );
	}

	public function test_eligible_when_the_quantity_meets_the_minimum_cart_quantity(): void {
		$settings = $this->settings( 0.0, 3 );

		$this->assertTrue( $this->subject()->is_eligible( 0.0, 3, $settings ) );
	}

	public function test_ineligible_when_only_the_quantity_minimum_is_unmet_but_amount_is_configured_too(): void {
		$settings = $this->settings( 10.0, 5 );

		$this->assertFalse( $this->subject()->is_eligible( 100.0, 4, $settings ) );
	}
}
