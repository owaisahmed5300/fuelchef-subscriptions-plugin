<?php
/**
 * Timestamped contract.
 */

declare(strict_types=1);

namespace FuelChef\Subscriptions\Contracts;

use FuelChef\Subscriptions\Values\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * An entity whose row records when it was created and last updated.
 */
interface Timestamped {

	/**
	 * When the row was created, or null before it has been persisted.
	 */
	public function date_created(): ?DateTime;

	/**
	 * Sets when the row was created.
	 */
	public function set_date_created( ?DateTime $date_created ): static;

	/**
	 * When the row was last updated, or null before it has been persisted.
	 */
	public function date_updated(): ?DateTime;

	/**
	 * Sets when the row was last updated.
	 */
	public function set_date_updated( ?DateTime $date_updated ): static;
}
