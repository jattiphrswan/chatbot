<?php
/**
 * Fired during plugin deactivation.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation routines.
 */
class Deactivator {

	/**
	 * Short description of what the deactivate method does.
	 *
	 * Cleans temporary transients, flushes rewrite rules if needed.
	 */
	public static function deactivate(): void {
		// Clean transients or temporary locks.
		flush_rewrite_rules();
	}
}
