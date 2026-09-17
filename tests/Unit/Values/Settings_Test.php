<?php
/**
 * Unit tests for the settings value object.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Values;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Tests\TestCase;
use FuelChef\Subscriptions\Values\Settings;
use FuelChef\Subscriptions\Values\Subscribe_Applicability;
use InvalidArgumentException;

/**
 * @covers \FuelChef\Subscriptions\Values\Settings
 */
final class Settings_Test extends TestCase {


	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	/**
	 * Every constructor argument, in order, for a value known to be valid - so each test
	 * only has to override the one argument it is checking.
	 *
	 * @return array{0: int, 1: string, 2: int, 3: string, 4: int, 5: string, 6: string, 7: string, 8: string}
	 */
	private function valid_args(): array {
		return [ 1, '17:00:00', 5, Subscribe_Applicability::INITIAL_AND_RENEWALS, 60, 'Fulfilment date', '', 'Subscribe & Save {percent}%', '' ];
	}

	public function test_accepts_valid_values(): void {
		$settings = new Settings( ...$this->valid_args() );

		$this->assertSame( 1, $settings->cutoff_days() );
		$this->assertSame( '17:00:00', $settings->cutoff_time() );
		$this->assertSame( 5, $settings->subscribe_discount_percent() );
		$this->assertSame( Subscribe_Applicability::INITIAL_AND_RENEWALS, $settings->subscribe_applicability() );
		$this->assertSame( 60, $settings->max_fulfilment_window_days() );
		$this->assertSame( 'Fulfilment date', $settings->fulfilment_date_label() );
		$this->assertSame( '', $settings->fulfilment_date_description() );
		$this->assertSame( 'Subscribe & Save {percent}%', $settings->subscribe_save_label() );
		$this->assertSame( '', $settings->subscribe_save_description() );
		$this->assertSame( 0.0, $settings->minimum_order_amount() );
		$this->assertSame( 0, $settings->minimum_cart_quantity() );
		$this->assertSame( '', $settings->ineligible_message() );
	}

	public function test_accepts_custom_eligibility_values(): void {
		$settings = new Settings(
			...$this->valid_args(),
			minimum_order_amount: 50.0,
			minimum_cart_quantity: 3,
			ineligible_message: 'Add more to unlock this.'
		);

		$this->assertSame( 50.0, $settings->minimum_order_amount() );
		$this->assertSame( 3, $settings->minimum_cart_quantity() );
		$this->assertSame( 'Add more to unlock this.', $settings->ineligible_message() );
	}

	public function test_ineligible_message_resolved_falls_back_to_the_default_when_empty(): void {
		$settings = new Settings( ...$this->valid_args() );

		$this->assertNotSame( '', $settings->ineligible_message_resolved() );
	}

	public function test_ineligible_message_resolved_returns_the_custom_message_when_set(): void {
		$settings = new Settings( ...$this->valid_args(), ineligible_message: 'Add more to unlock this.' );

		$this->assertSame( 'Add more to unlock this.', $settings->ineligible_message_resolved() );
	}

	public function test_rejects_a_negative_minimum_order_amount(): void {
		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$this->valid_args(), minimum_order_amount: -0.01 );
	}

	public function test_rejects_a_negative_minimum_cart_quantity(): void {
		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$this->valid_args(), minimum_cart_quantity: -1 );
	}

	public function test_rejects_an_ineligible_message_over_the_length_limit(): void {
		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$this->valid_args(), ineligible_message: str_repeat( 'a', Settings::MAX_DESCRIPTION_LENGTH + 1 ) );
	}

	public function test_subscribe_save_label_resolved_replaces_the_percent_placeholder(): void {
		$args    = $this->valid_args();
		$args[2] = 12;
		$args[7] = 'Subscribe & Save {percent}% today';

		$settings = new Settings( ...$args );

		$this->assertSame( 'Subscribe & Save 12% today', $settings->subscribe_save_label_resolved() );
	}

	public function test_subscribe_save_description_resolved_replaces_the_percent_placeholder(): void {
		$args    = $this->valid_args();
		$args[2] = 12;
		$args[8] = 'Save {percent}% on this order and every renewal.';

		$settings = new Settings( ...$args );

		$this->assertSame( 'Save 12% on this order and every renewal.', $settings->subscribe_save_description_resolved() );
	}

	public function test_subscribe_save_description_resolved_stays_empty_when_unset(): void {
		$settings = new Settings( ...$this->valid_args() );

		$this->assertSame( '', $settings->subscribe_save_description_resolved() );
	}

	public function test_rejects_a_negative_cutoff_days(): void {
		$args    = $this->valid_args();
		$args[0] = -1;

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_an_invalid_cutoff_time(): void {
		$args    = $this->valid_args();
		$args[1] = 'not-a-time';

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_a_discount_percent_above_100(): void {
		$args    = $this->valid_args();
		$args[2] = 101;

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_an_unknown_applicability(): void {
		$args    = $this->valid_args();
		$args[3] = 'every_third_order';

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_a_max_fulfilment_window_of_zero_days(): void {
		$args    = $this->valid_args();
		$args[4] = 0;

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_a_max_fulfilment_window_beyond_the_ceiling(): void {
		$args    = $this->valid_args();
		$args[4] = Settings::MAX_FULFILMENT_WINDOW_DAYS + 1;

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_accepts_a_max_fulfilment_window_at_exactly_the_ceiling(): void {
		$args    = $this->valid_args();
		$args[4] = Settings::MAX_FULFILMENT_WINDOW_DAYS;

		$settings = new Settings( ...$args );

		$this->assertSame( Settings::MAX_FULFILMENT_WINDOW_DAYS, $settings->max_fulfilment_window_days() );
	}

	public function test_rejects_a_blank_fulfilment_date_label(): void {
		$args    = $this->valid_args();
		$args[5] = '   ';

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_a_fulfilment_date_label_over_the_length_limit(): void {
		$args    = $this->valid_args();
		$args[5] = str_repeat( 'a', Settings::MAX_LABEL_LENGTH + 1 );

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_a_fulfilment_date_description_over_the_length_limit(): void {
		$args    = $this->valid_args();
		$args[6] = str_repeat( 'a', Settings::MAX_DESCRIPTION_LENGTH + 1 );

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_a_blank_subscribe_save_label(): void {
		$args    = $this->valid_args();
		$args[7] = '';

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_rejects_a_subscribe_save_description_over_the_length_limit(): void {
		$args    = $this->valid_args();
		$args[8] = str_repeat( 'a', Settings::MAX_DESCRIPTION_LENGTH + 1 );

		$this->expectException( InvalidArgumentException::class );

		new Settings( ...$args );
	}

	public function test_accepts_an_empty_description_at_exactly_the_length_limit(): void {
		$args    = $this->valid_args();
		$args[6] = str_repeat( 'a', Settings::MAX_DESCRIPTION_LENGTH );

		$settings = new Settings( ...$args );

		$this->assertSame( $args[6], $settings->fulfilment_date_description() );
	}
}
