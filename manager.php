<?php

/* Require plug-in files */
require_once __DIR__ . '/integrations/distributor.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/admin.php';

/* Call the setup functions */
\DT\NbAddon\DTInBackground\Distributor\setup();

/* Require add-ons integration files aren't disabled (enabled by default) */
$settings = \DT\NbAddon\DTInBackground\Helpers\get_options();

if( empty( $settings['disable_for_clone_fix'] ) ) {
	require_once __DIR__ . '/integrations/clone-fix.php';
	\DT\NbAddon\DTInBackground\CloneFix\setup();
}

if( empty( $settings['disable_for_wc'] ) ) {
	require_once __DIR__ . '/integrations/woocommerce.php';
	\DT\NbAddon\DTInBackground\WC\setup();
}
