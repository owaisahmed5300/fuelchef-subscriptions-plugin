<?php
/**
 * Unit tests for checkout shortcode/block presence detection.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Services\Checkout;

use Brain\Monkey\Functions;
use FuelChef\Subscriptions\Services\Checkout\Checkout_Presence_Service;
use FuelChef\Subscriptions\Tests\TestCase;
use WP_Post;

/**
 * @covers \FuelChef\Subscriptions\Services\Checkout\Checkout_Presence_Service
 */
final class Checkout_Presence_Service_Test extends TestCase {


	private function subject(): Checkout_Presence_Service {
		return new Checkout_Presence_Service();
	}

	public function test_has_classic_shortcode_is_true_when_the_post_content_has_it(): void {
		$post               = new WP_Post();
		$post->post_content = '[woocommerce_checkout]';

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\expect( 'has_shortcode' )->once()
			->with( '[woocommerce_checkout]', 'woocommerce_checkout' )
			->andReturn( true );

		$this->assertTrue( $this->subject()->has_classic_shortcode() );
	}

	public function test_has_classic_shortcode_is_false_without_a_current_post(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$this->assertFalse( $this->subject()->has_classic_shortcode() );
	}

	public function test_has_block_is_true_when_the_post_has_the_checkout_block(): void {
		$post = new WP_Post();

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\expect( 'has_block' )->once()
			->with( 'woocommerce/checkout', $post )
			->andReturn( true );

		$this->assertTrue( $this->subject()->has_block() );
	}

	public function test_has_block_is_false_without_a_current_post(): void {
		Functions\when( 'get_post' )->justReturn( null );

		$this->assertFalse( $this->subject()->has_block() );
	}

	public function test_has_either_is_true_when_only_the_shortcode_is_present(): void {
		$post = new WP_Post();

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'has_shortcode' )->justReturn( true );
		Functions\when( 'has_block' )->justReturn( false );

		$this->assertTrue( $this->subject()->has_either() );
	}

	public function test_has_either_is_false_when_neither_is_present(): void {
		$post = new WP_Post();

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'has_shortcode' )->justReturn( false );
		Functions\when( 'has_block' )->justReturn( false );

		$this->assertFalse( $this->subject()->has_either() );
	}

	public function test_results_are_memoized_per_request(): void {
		$post = new WP_Post();

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\expect( 'has_shortcode' )->once()->andReturn( true );

		$subject = $this->subject();

		$this->assertTrue( $subject->has_classic_shortcode() );
		$this->assertTrue( $subject->has_classic_shortcode() );
	}
}
