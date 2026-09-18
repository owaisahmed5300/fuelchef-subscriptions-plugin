<?php
/**
 * Classic checkout subscribe-and-save logged-out message.
 *
 * $data carries `message_html` (string) - already safely escaped, with the "Log in" link
 * either substituted in or appended, per `Subscribe_And_Save::logged_out_message_html()`.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** @var string $message_html */
$message_html = $data['message_html'];
?>
<p class="fcs-subscribe-and-save-logged-out">
	<?php echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</p>
