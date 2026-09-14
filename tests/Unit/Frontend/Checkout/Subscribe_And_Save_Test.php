<?php
/**
 * Unit tests for the subscribe-and-save checkout discount.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Frontend\Checkout;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save;
use FuelChef\Subscriptions\Services\Settings_Store;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Utils\Renderer;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;

/**
 * @covers \FuelChef\Subscriptions\Frontend\Checkout\Subscribe_And_Save
 */
final class Subscribe_And_Save_Test extends TestCase {


	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wc_get_price_decimals' )->justReturn( 2 );
	}

	private function settings( int $percent, string $applicability ): Settings {
		return new Settings( 1, '17:00:00', $percent, $applicability );
	}

	private function subject(): Subscribe_And_Save {
		return new Subscribe_And_Save( new Settings_Store(), new Renderer( __DIR__ ) );
	}

	public function test_discount_amount_is_a_percentage_of_the_subtotal(): void {
		$settings = $this->settings( 10, Subscribe_Applicability::INITIAL_AND_RENEWALS );

		$this->assertSame( 10.0, $this->subject()->discount_amount( 100.0, $settings ) );
	}

	public function test_discount_amount_is_rounded_to_the_store_price_decimals(): void {
		$settings = $this->settings( 5, Subscribe_Applicability::INITIAL_AND_RENEWALS );

		$this->assertSame( 4.5, $this->subject()->discount_amount( 89.99, $settings ) );
	}

	public function test_discount_amount_is_zero_when_applicability_is_renewal_only(): void {
		$settings = $this->settings( 10, Subscribe_Applicability::RENEWAL_ONLY );

		$this->assertSame( 0.0, $this->subject()->discount_amount( 100.0, $settings ) );
	}

	public function test_discount_amount_is_zero_when_the_percent_is_zero(): void {
		$settings = $this->settings( 0, Subscribe_Applicability::INITIAL_AND_RENEWALS );

		$this->assertSame( 0.0, $this->subject()->discount_amount( 100.0, $settings ) );
	}

	public function test_discount_amount_is_zero_when_the_subtotal_is_zero(): void {
		$settings = $this->settings( 10, Subscribe_Applicability::INITIAL_AND_RENEWALS );

		$this->assertSame( 0.0, $this->subject()->discount_amount( 0.0, $settings ) );
	}
}
