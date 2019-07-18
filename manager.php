<?php

/* Require plug-in files */
require_once __DIR__ . '/integrations/distributor.php';
require_once __DIR__ . '/helpers.php';

/* Call the setup functions */
\DT\NbAddon\DTInBackground\Distributor\setup();

/* Require add-ons integration in case they are activated */
if( function_exists( 'dt_clone_fix_add_on_bootstrap' ) ) {
	require_once __DIR__ . '/integrations/clone-fix.php';
	\DT\NbAddon\DTInBackground\CloneFix\setup();
}

if( function_exists( 'dt_wc_add_on_bootstrap' ) ) {
	require_once __DIR__ . '/integrations/woocommerce.php';
	\DT\NbAddon\DTInBackground\WC\setup();
}
