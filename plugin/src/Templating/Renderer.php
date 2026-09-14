<?php
/**
 * Template renderer.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Templating;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a PHP template under `plugin/templates/` and returns the output as a string.
 *
 * Shared by admin screens and the frontend checkout - neither renders HTML directly, so
 * a template is a plain PHP file that reads its data from `$data`, never a magic
 * variable extracted into scope.
 */
final class Renderer {


	/**
	 * Creates a renderer.
	 *
	 * @param string $base_dir Absolute path templates are resolved under, no trailing
	 *                         slash.
	 */
	public function __construct(
		private string $base_dir
	) {
	}

	/**
	 * Renders a template to a string.
	 *
	 * @param string               $template Template path, relative to `templates/` and
	 *                                        without the `.php` extension, e.g.
	 *                                        `admin/schedules`.
	 * @param array<string, mixed> $data Data available to the template as `$data`.
	 *
	 * @return string The rendered output.
	 *
	 * @throws RuntimeException When the template file does not exist.
	 */
	public function render( string $template, array $data = [] ): string {
		$path = $this->path_for( $template );

		if ( ! file_exists( $path ) ) {
			throw new RuntimeException(
				sprintf( 'Template "%s" does not exist at %s.', $template, $path )
			);
		}

		ob_start();

		// $data is not referenced by name here; the included template reads it from scope.
		( static function ( string $__path, array $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			include $__path;
		} )( $path, $data );

		return (string) ob_get_clean();
	}

	/**
	 * The absolute path for a template name.
	 */
	private function path_for( string $template ): string {
		return $this->base_dir . '/' . $template . '.php';
	}
}
