<?php
/**
 * Classic checkout subscribe-and-save logged-out message.
 *
 * $data carries `message` (string) and `login_url` (string).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** @var string $message */
$message = $data['message'];
/** @var string $login_url */
$login_url = $data['login_url'];
?>
<p class="fcs-subscribe-and-save-logged-out">
	<?php echo esc_html( $message ); ?>
	<a href="<?php echo esc_url( $login_url ); ?>" class="fcs-subscribe-and-save-logged-out__link">
		<?php esc_html_e( 'Log in', 'fuelchef-subscriptions' ); ?>
	</a>
</p>
