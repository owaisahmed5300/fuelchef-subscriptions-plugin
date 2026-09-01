<?php
/**
 * Route all outgoing mail to the Mailpit container during local development.
 *
 * WordPress' wp_mail() ultimately calls PHPMailer; hooking phpmailer_init lets us point
 * it at Mailpit's SMTP server (host "mailpit", port 1025) so nothing ever leaves the
 * Docker network and every message is viewable at http://localhost:8025.
 *
 * This is a Must-Use plugin mounted only by the dev Docker stack — it ships with the
 * environment, not with the plugin, and is never part of a release.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action(
	'phpmailer_init',
	static function ( $phpmailer ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host        = getenv( 'MAILPIT_SMTP_HOST' ) ?: 'mailpit';
		$phpmailer->Port        = (int) ( getenv( 'MAILPIT_SMTP_PORT' ) ?: 1025 );
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPSecure  = '';
		$phpmailer->SMTPAutoTLS = false;
	}
);
