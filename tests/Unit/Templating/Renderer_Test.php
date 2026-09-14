<?php
/**
 * Unit tests for the template renderer.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Tests\Unit\Templating;

use FuelChef\Subscriptions\Templating\Renderer;
use FuelChef\Subscriptions\Tests\TestCase;
use RuntimeException;

/**
 * @covers \FuelChef\Subscriptions\Templating\Renderer
 */
final class Renderer_Test extends TestCase {


	/**
	 * Directory this test writes fixture templates into.
	 */
	private string $base_dir;

	protected function setUp(): void {
		parent::setUp();

		$this->base_dir = sys_get_temp_dir() . '/fcs-renderer-test-' . uniqid();

		mkdir( $this->base_dir, 0777, true );
	}

	protected function tearDown(): void {
		array_map( 'unlink', glob( $this->base_dir . '/*.php' ) ?: [] );
		rmdir( $this->base_dir );

		parent::tearDown();
	}

	private function write_template( string $name, string $contents ): void {
		file_put_contents( $this->base_dir . '/' . $name . '.php', $contents );
	}

	public function test_render_returns_the_templates_output(): void {
		$this->write_template( 'greeting', '<?php echo "Hello"; ?>' );

		$output = ( new Renderer( $this->base_dir ) )->render( 'greeting' );

		$this->assertSame( 'Hello', $output );
	}

	public function test_render_makes_data_available_as_data(): void {
		$this->write_template( 'greeting', '<?php echo "Hello, " . $data[\'name\']; ?>' );

		$output = ( new Renderer( $this->base_dir ) )->render( 'greeting', [ 'name' => 'Ada' ] );

		$this->assertSame( 'Hello, Ada', $output );
	}

	public function test_render_throws_when_the_template_does_not_exist(): void {
		$this->expectException( RuntimeException::class );

		( new Renderer( $this->base_dir ) )->render( 'missing' );
	}
}
