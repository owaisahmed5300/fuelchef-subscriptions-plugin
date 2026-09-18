<?php
/**
 * Classic checkout subscribe-and-save logged-out message.
 *
 * This template can be overridden by copying it to
 * yourtheme/fuelchef-subscriptions/checkout/subscribe-and-save-logged-out.php.
 *
 * @var string $message The logged-out message.
 * @var string $login_url The login URL, redirecting back to checkout once signed in.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<p class="fcs-subscribe-and-save-logged-out">
	<?php echo esc_html( $message ); ?>
	<a href="<?php echo esc_url( $login_url ); ?>" class="fcs-subscribe-and-save-logged-out__link">
		<?php esc_html_e( 'Log in', 'fuelchef-subscriptions' ); ?>
	</a>
</p>
