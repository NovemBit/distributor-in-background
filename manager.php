<?php
/**
 * Manage plugin files
 *
 * @package distributor-in-background
 */

/* Require plug-in files */
require_once __DIR__ . '/integrations/distributor.php';
require_once __DIR__ . '/helpers.php';

/* Call the setup functions */
\DT\NbAddon\DTInBackground\Distributor\setup();

/* Require add-ons integration in case they are activated */
if ( function_exists( 'dt_clone_fix_add_on_bootstrap' ) ) {
	require_once __DIR__ . '/integrations/clone-fix.php';
	\DT\NbAddon\DTInBackground\CloneFix\setup();
}

if ( function_exists( 'dt_wc_add_on_bootstrap' ) ) {
	require_once __DIR__ . '/integrations/woocommerce.php';
	\DT\NbAddon\DTInBackground\WC\setup();
}

if ( function_exists( 'dt_comments_add_on_bootstrap' ) ) {
	require_once __DIR__ . '/integrations/comments.php';
	\DT\NbAddon\DTInBackground\Comments\setup();
}

if ( function_exists( 'dt_groups_taxonomy_add_on_bootstrap' ) ) {
	require_once __DIR__ . '/integrations/groups-taxonomy.php';
	\DT\NbAddon\DTInBackground\GroupsTaxonomy\setup();
}
