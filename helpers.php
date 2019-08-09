<?php
/**
 * Helper functions
 *
 * @package distributor-in-background
 */

namespace DT\NbAddon\DTInBackground\Helpers;

/**
 * Helper function to determine whether 'wp task manager' plug-in is active or not
 *
 * @return bool
 */
function is_btm_active() {
	return defined( 'BTM_PLUGIN_ACTIVE' ) && true === BTM_PLUGIN_ACTIVE;
}
