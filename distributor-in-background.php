<?php
/**
 * Plugin Name:       Distributor in Background
 * Description:       An add-on for the "Distributor" plug-in to run resource distribution in background as scheduled tasks
 * Version:           1.0.0
 * Author:            Novembit
 * License:           GPLv2 or later
 * Text Domain:       distributor-bg
 */

/**
 * Bootstrap function
 */
function dt_bg_add_on_bootstrap() {
	if ( ! function_exists( '\Distributor\ExternalConnectionCPT\setup' ) ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', function() {
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-error' ), esc_html( 'You need to have Distributor plug-in activated to run the Distributor WooCommerce Add-on.', 'distributor-acf' ) );
			} );
		}
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'manager.php';
}

add_action( 'plugins_loaded', 'dt_bg_add_on_bootstrap' );
