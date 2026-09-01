<?php
/**
 * Base test case.
 */

declare(strict_types=1);

namespace WPPluginBoilerplate\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;

/**
 * Brings Brain Monkey up and down around every test.
 *
 * The file name does not end in `Test.php`, so PHPUnit never collects it.
 */
abstract class TestCase extends PHPUnit_TestCase {

	/**
	 * Counts Mockery expectations as assertions and verifies them on teardown.
	 *
	 * Without it an unmet `Functions\expect()` would pass silently.
	 */
	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}
}
