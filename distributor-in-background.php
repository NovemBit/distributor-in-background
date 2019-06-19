<?php
/**
 * Plugin Name:       Distributor in Background
 * Description:       An add-on for the "Distributor" plug-in to run resource distribution in background as scheduled tasks
 * Version:           1.0.0
 * Author:            Novembit
 * License:           GPLv2 or later
 * Text Domain:       distributor-bg
 */

/* Bail out if the "parent" plug-in insn't active */
require_once ABSPATH . '/wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'distributor-adapted/distributor.php' ) ) {
	return;
}

require_once plugin_dir_path( __FILE__ ) . 'hooks.php';

\DistributorInBackground\Hooks\setup();