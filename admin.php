<?php

namespace DT\NbAddon\DTInBackground\Admin;

/**
 * Hook up actions
 */
add_action( 'admin_menu', __NAMESPACE__ . '\admin_menu', 20 );
add_action( 'admin_init', __NAMESPACE__ . '\setup_fields_sections' );


function admin_menu() {
	add_submenu_page( 'distributor', esc_html__( 'Distributor in Background', 'distributor-bg' ), esc_html__( 'Distributor in Background', 'distributor-bg' ), 'manage_options', 'distributor-in-bg', __NAMESPACE__ . '\settings_screen' );
}

function settings_screen() {
	?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Distributor in Background Settings', 'distributor' ); ?></h2>

		<form action="options.php" method="post">

			<?php settings_fields( 'dtbg_settings' ); ?>
			<?php do_settings_sections( 'distributor-in-bg' ); ?>

			<?php submit_button(); ?>

		</form>
	</div>
	<?php
}

function setup_fields_sections() {
	add_settings_section( 'dt-bg-section', esc_html__( 'Distributor in Background settings', 'distributor-bg' ), '', 'distributor-in-bg' );

	add_settings_field( 'disable_for_clone_fix', esc_html__( 'Disable for Clone Fix', 'distributor-bg' ), __NAMESPACE__ . '\disable_clone_fix', 'distributor-in-bg', 'dt-bg-section' );

	add_settings_field( 'disable_for_wc', esc_html__( 'Disable for WooCommerce', 'distributor-bg' ), __NAMESPACE__ . '\disable_wc', 'distributor-in-bg', 'dt-bg-section' );

	register_setting( 'dtbg_settings', 'dtbg_settings' );
}

function register_settings() {
	register_setting( 'dtbg_settings', 'dtbg_settings' );
}

function disable_clone_fix() {
	$settings = get_option( 'dtbg_settings', [] );
	?>
	<label><input <?php checked( isset($settings['disable_for_clone_fix'] ), true ); ?> type="checkbox" value="1" name="dtbg_settings[disable_for_clone_fix]">
		<?php esc_html_e( 'Disable "distribution in background" for Distributor Clone Fix add-on (if isn\'t installed/activated).', 'distributor-bg' ); ?>
	</label>
	<?php
}

function disable_wc() {
	$settings = get_option( 'dtbg_settings', [] );
	?>
	<label><input <?php checked( isset($settings['disable_for_wc'] ), true ); ?> type="checkbox" value="1" name="dtbg_settings[disable_for_wc]">
		<?php esc_html_e( 'Disable "distribution in background" for Distributor WooCommerce add-on (if isn\'t installed/activated).', 'distributor-bg' ); ?>
	</label>
	<?php
}
