<?php
/**
 * Template renderer.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Utils;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a PHP template under `plugin/templates/` and returns the output as a string.
 *
 * Shared by admin screens and the frontend checkout. WooCommerce-style, not a `$data`
 * array: each key of the given data becomes its own variable in the template's scope
 * (`'order' => $order` reads back as `$order`), so a template gets IDE autocompletion and
 * type hints from its own `@var` block instead of `$data['order']` lookups.
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
	 * @param array<string, mixed> $data Each entry is extracted into its own variable in
	 *                                   the template's scope, keyed by name - see the
	 *                                   class docblock.
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

		( static function ( string $__path, array $__data ): void {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- the whole point of this method: see the class docblock.
			extract( $__data, EXTR_SKIP );

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
