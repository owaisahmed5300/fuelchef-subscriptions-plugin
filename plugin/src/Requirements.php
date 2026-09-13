<?php
/**
 * Pre-flight requirements gate.
 *
 * Loaded before the Composer autoloader, so it can have no dependencies.
 * Must parse and run on PHP 5.3: its purpose is to determine whether the
 * plugin meets its minimum requirements, including the minimum PHP version.
 */

namespace FuelChef\Subscriptions;

defined( 'ABSPATH' ) || exit;

/**
 * Checks everything that must be true before the plugin may load.
 *
 * Configure it, then ask once:
 *
 *     $requirements = Requirements::for_plugin( __FILE__, 'My Plugin' )
 *         ->require_php( '8.0' )
 *         ->require_wp( '6.5' )
 *         ->require_plugin( 'woocommerce', 'WooCommerce', '10.0' )
 *         ->require_autoloader( __DIR__ . '/vendor-prefixed/autoload.php' );
 *
 *     if ( ! $requirements->satisfied() ) {
 *         return;
 *     }
 */
final class Requirements {


	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin display name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Minimum PHP version, or an empty string when unchecked.
	 *
	 * @var string
	 */
	private $min_php = '';

	/**
	 * Minimum WordPress version, or an empty string when unchecked.
	 *
	 * @var string
	 */
	private $min_wp = '';

	/**
	 * Required plugins, keyed by WordPress.org slug.
	 *
	 * @var array<string, array{name: string, min_version: string}>
	 */
	private $required_plugins = array();

	/**
	 * Candidate autoloader paths, most specific first.
	 *
	 * @var list<string>
	 */
	private $autoloader_candidates = array();

	/**
	 * Cached failures, or null before the checks have run.
	 *
	 * @var list<array<string, string>>|null
	 */
	private $failures = null;

	/**
	 * Cached result of get_plugins().
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private $installed_plugins = null;

	/**
	 * Whether the failure hooks have been registered.
	 *
	 * @var bool
	 */
	private $handled = false;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $plugin_name Plugin display name.
	 */
	private function __construct( $plugin_file, $plugin_name ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_name = $plugin_name;
	}

	/**
	 * Begin declaring the requirements for a plugin.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $plugin_name Plugin display name.
	 *
	 * @return self
	 */
	public static function for_plugin( $plugin_file, $plugin_name ) {
		return new self( $plugin_file, $plugin_name );
	}

	/**
	 * Require a minimum PHP version.
	 *
	 * @param string $version Minimum PHP version.
	 *
	 * @return self
	 */
	public function require_php( $version ) {
		$this->min_php = $version;

		return $this;
	}

	/**
	 * Require a minimum WordPress version.
	 *
	 * @param string $version Minimum WordPress version.
	 *
	 * @return self
	 */
	public function require_wp( $version ) {
		$this->min_wp = $version;

		return $this;
	}

	/**
	 * Require another plugin, optionally at a minimum version.
	 *
	 * @param string $slug Plugin slug on WordPress.org, e.g. `woocommerce`.
	 * @param string $name Plugin display name, e.g. `WooCommerce`.
	 * @param string $min_version Minimum version, or an empty string for any.
	 *
	 * @return self
	 */
	public function require_plugin( $slug, $name, $min_version = '' ) {
		$this->required_plugins[ $slug ] = array(
			'name'        => $name,
			'min_version' => $min_version,
		);

		return $this;
	}

	/**
	 * Require a Composer autoloader, given candidate paths in preference order.
	 *
	 * Accepts any number of string paths, most specific first; the first that
	 * exists on disk is the one that loads. Variadic by `func_get_args()` rather
	 * than `...$paths`, which is PHP 5.6+.
	 *
	 * @return self
	 */
	public function require_autoloader() {
		/** @var list<string> $paths */
		$paths                       = func_get_args();
		$this->autoloader_candidates = $paths;

		return $this;
	}

	/**
	 * Whether every requirement is met.
	 *
	 * On the first call, when anything is unmet, this also registers the admin
	 * notice and the activation guard, so the caller only has to `return`.
	 * The side effect is deliberate and happens at most once.
	 *
	 * @return bool
	 */
	public function satisfied() {
		if ( array() === $this->failures() ) {
			return true;
		}

		$this->register_failure_handlers();

		return false;
	}

	/**
	 * Every unmet requirement, in the order they were checked.
	 *
	 * Each entry has a `type` key: `php`, `wp`, `plugin_missing`,
	 * `plugin_inactive`, `plugin_version` or `autoloader`.
	 *
	 * @return list<array<string, string>>
	 */
	public function failures() {
		if ( null !== $this->failures ) {
			return $this->failures;
		}

		$this->failures = array_merge(
			$this->php_failures(),
			$this->wordpress_failures(),
			$this->plugin_failures(),
			$this->autoloader_failures()
		);

		return $this->failures;
	}

	/**
	 * The first autoloader candidate that exists, or an empty string.
	 *
	 * @return string
	 */
	public function autoloader() {
		foreach ( $this->autoloader_candidates as $candidate ) {
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Render the admin notice.
	 *
	 * @return void
	 */
	public function render_notice() {
		$failures = $this->failures();

		if ( array() === $failures ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';

		printf(
		/* translators: %s: Plugin name. */
			esc_html__( '%s could not be loaded.', 'fuelchef-subscriptions' ),
			esc_html( $this->plugin_name )
		);

		echo '</strong></p>';

		if ( 1 === count( $failures ) ) {
			echo '<p>' . wp_kses_post( $this->message( $failures[0] ) ) . '</p>';
			echo '</div>';

			return;
		}

		echo '<p>' . esc_html__( 'The following requirements are not met:', 'fuelchef-subscriptions' ) . '</p>';
		echo '<ul>';

		foreach ( $failures as $failure ) {
			echo '<li>' . wp_kses_post( $this->message( $failure ) ) . '</li>';
		}

		echo '</ul></div>';
	}

	/**
	 * Stop activation with an explanation.
	 *
	 * @return void
	 */
	public function block_activation() {
		$messages = array();

		foreach ( $this->failures() as $failure ) {
			$messages[] = '<li>' . $this->message( $failure ) . '</li>';
		}

		$body = sprintf(
				/* translators: %s: Plugin name. */
			'<p>' . esc_html__( '%s could not be activated.', 'fuelchef-subscriptions' ) . '</p>',
			esc_html( $this->plugin_name )
		) . '<ul>' . implode( '', $messages ) . '</ul>';

		wp_die(
			wp_kses_post( $body ),
			esc_html__( 'Plugin activation failed', 'fuelchef-subscriptions' ),
			array( 'back_link' => true )
		);
	}

	/**
	 * Register the notice and activation guard, at most once.
	 *
	 * @return void
	 */
	private function register_failure_handlers() {
		if ( $this->handled ) {
			return;
		}

		$this->handled = true;

		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		register_activation_hook( $this->plugin_file, array( $this, 'block_activation' ) );
	}

	/**
	 * Check the PHP version.
	 *
	 * @return list<array<string, string>>
	 */
	private function php_failures() {
		if ( '' === $this->min_php || version_compare( PHP_VERSION, $this->min_php, '>=' ) ) {
			return array();
		}

		return array(
			array(
				'type'     => 'php',
				'required' => $this->min_php,
				'current'  => PHP_VERSION,
			),
		);
	}

	/**
	 * Check the WordPress version.
	 *
	 * @return list<array<string, string>>
	 */
	private function wordpress_failures() {
		if ( '' === $this->min_wp ) {
			return array();
		}

		$current = get_bloginfo( 'version' );

		if ( version_compare( $current, $this->min_wp, '>=' ) ) {
			return array();
		}

		return array(
			array(
				'type'     => 'wp',
				'required' => $this->min_wp,
				'current'  => $current,
			),
		);
	}

	/**
	 * Check every required plugin is installed, active and new enough.
	 *
	 * @return list<array<string, string>>
	 */
	private function plugin_failures() {
		$failures = array();

		foreach ( $this->required_plugins as $slug => $plugin ) {
			$file = $this->plugin_file_for( $slug );

			if ( null === $file ) {
				$failures[] = array(
					'type' => 'plugin_missing',
					'slug' => $slug,
					'name' => $plugin['name'],
				);

				continue;
			}

			if ( ! $this->is_plugin_active( $file ) ) {
				$failures[] = array(
					'type' => 'plugin_inactive',
					'slug' => $slug,
					'name' => $plugin['name'],
					'file' => $file,
				);

				continue;
			}

			if ( '' === $plugin['min_version'] ) {
				continue;
			}

			$current = $this->installed_version( $file );

			if ( version_compare( $current, $plugin['min_version'], '>=' ) ) {
				continue;
			}

			$failures[] = array(
				'type'     => 'plugin_version',
				'slug'     => $slug,
				'name'     => $plugin['name'],
				'file'     => $file,
				'required' => $plugin['min_version'],
				'current'  => $current,
			);
		}

		return $failures;
	}

	/**
	 * Check that a Composer autoloader was shipped.
	 *
	 * @return list<array<string, string>>
	 */
	private function autoloader_failures() {
		if ( array() === $this->autoloader_candidates || '' !== $this->autoloader() ) {
			return array();
		}

		return array(
			array(
				'type' => 'autoloader',
			),
		);
	}

	/**
	 * Build the human-readable message for one failure.
	 *
	 * @param array<string, string> $failure One entry from `failures()`.
	 *
	 * @return string
	 */
	private function message( array $failure ) {
		switch ( $failure['type'] ) {
			case 'php':
				return sprintf(
				/* translators: 1: Plugin name. 2: Required PHP version. 3: Installed PHP version. */
					esc_html__(
						'%1$s requires PHP %2$s or later. This site is running PHP %3$s. Please ask your host to upgrade.',
						'fuelchef-subscriptions'
					),
					esc_html( $this->plugin_name ),
					esc_html( $failure['required'] ),
					esc_html( $failure['current'] )
				);

			case 'wp':
				return sprintf(
				/* translators: 1: Plugin name. 2: Required WordPress version. 3: Installed WordPress version. */
					esc_html__(
						'%1$s requires WordPress %2$s or later. This site is running WordPress %3$s.',
						'fuelchef-subscriptions'
					),
					esc_html( $this->plugin_name ),
					esc_html( $failure['required'] ),
					esc_html( $failure['current'] )
				);

			case 'plugin_missing':
				return $this->plugin_action_message(
					sprintf(
					/* translators: 1: Plugin name. 2: Required plugin name. */
						esc_html__( '%1$s requires %2$s, which is not installed.', 'fuelchef-subscriptions' ),
						esc_html( $this->plugin_name ),
						esc_html( $failure['name'] )
					),
					'install_plugins',
					$this->install_url( $failure['slug'] ),
					esc_html__( 'Install Plugin', 'fuelchef-subscriptions' )
				);

			case 'plugin_inactive':
				return $this->plugin_action_message(
					sprintf(
					/* translators: 1: Plugin name. 2: Required plugin name. */
						esc_html__(
							'%1$s requires %2$s, which is installed but not active.',
							'fuelchef-subscriptions'
						),
						esc_html( $this->plugin_name ),
						esc_html( $failure['name'] )
					),
					'activate_plugins',
					$this->activate_url( $failure['file'] ),
					esc_html__( 'Activate Plugin', 'fuelchef-subscriptions' )
				);

			case 'plugin_version':
				return sprintf(
				/* translators: 1: Plugin name. 2: Required plugin name. 3: Required version. 4: Installed version. */
					esc_html__(
						'%1$s requires %2$s %3$s or later. This site has %2$s %4$s.',
						'fuelchef-subscriptions'
					),
					esc_html( $this->plugin_name ),
					esc_html( $failure['name'] ),
					esc_html( $failure['required'] ),
					'' === $failure['current']
						? esc_html__( 'an unknown version', 'fuelchef-subscriptions' )
						: esc_html( $failure['current'] )
				);

			default:
				return sprintf(
				/* translators: %s: Plugin name. */
					esc_html__(
						'%s is missing its Composer dependencies. Please reinstall the plugin.',
						'fuelchef-subscriptions'
					),
					esc_html( $this->plugin_name )
				);
		}
	}

	/**
	 * Append an action link to a message when the user may act on it.
	 *
	 * @param string $message The message itself.
	 * @param string $capability Capability required to act.
	 * @param string $url Target URL.
	 * @param string $label Link label.
	 *
	 * @return string
	 */
	private function plugin_action_message( $message, $capability, $url, $label ) {
		if ( ! current_user_can( $capability ) ) {
			return $message;
		}

		return $message . ' <a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Find the plugin file for a slug, e.g. `woocommerce/woocommerce.php`.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return string|null
	 */
	private function plugin_file_for( $slug ) {
		$prefix = $slug . '/';

		foreach ( array_keys( $this->installed_plugins() ) as $file ) {
			if ( 0 === strpos( $file, $prefix ) ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Whether a plugin is active on this site or across the network.
	 *
	 * `is_plugin_active()` is itself defined as "active on this site, or active
	 * for the network", so it already covers both and needs no companion check.
	 *
	 * @param string $file Plugin file.
	 *
	 * @return bool
	 */
	private function is_plugin_active( $file ) {
		$this->load_plugin_functions();

		return is_plugin_active( $file );
	}

	/**
	 * Load WordPress plugin administration functions when necessary.
	 *
	 * @return void
	 */
	private function load_plugin_functions() {
		if ( function_exists( 'is_plugin_active' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * All installed plugins, read once per instance.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function installed_plugins() {
		if ( null === $this->installed_plugins ) {
			$this->load_plugin_functions();

			/** @var array<string, array<string, mixed>> $plugins */
			$plugins = get_plugins();

			$this->installed_plugins = $plugins;
		}

		return $this->installed_plugins;
	}

	/**
	 * The installed version of a plugin, or an empty string when undeclared.
	 *
	 * @param string $file Plugin file.
	 *
	 * @return string
	 */
	private function installed_version( $file ) {
		$plugins = $this->installed_plugins();
		$version = isset( $plugins[ $file ]['Version'] ) ? $plugins[ $file ]['Version'] : '';

		return is_string( $version ) ? $version : '';
	}

	/**
	 * Nonce-protected URL that installs a plugin from WordPress.org.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return string
	 */
	private function install_url( $slug ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'install-plugin',
					'plugin' => $slug,
				),
				self_admin_url( 'update.php' )
			),
			'install-plugin_' . $slug
		);
	}

	/**
	 * Nonce-protected URL that activates an installed plugin.
	 *
	 * @param string $file Plugin file.
	 *
	 * @return string
	 */
	private function activate_url( $file ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'activate',
					'plugin' => $file,
				),
				self_admin_url( 'plugins.php' )
			),
			'activate-plugin_' . $file
		);
	}
}
