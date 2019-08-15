<?php
/**
 * Handle Woocommerce integration
 *
 * @package distributor-in-background
 */

namespace DT\NbAddon\DTInBackground\WC;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_filter( 'dt_allow_wc_variations_update', __NAMESPACE__ . '\schedule_variation_update', 10, 3 );
			add_action( 'dt_wc_variation_update_hook', __NAMESPACE__ . '\variation_update', 10, 2 );
			add_filter( 'dt_allow_wc_variations_insert', __NAMESPACE__ . '\schedule_variation_insert', 10, 5 );
			add_action( 'dt_wc_variation_insert_hook', __NAMESPACE__ . '\variation_insert', 10, 4 );
			if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'wc_variations_update_in_bg', __NAMESPACE__ . '\bg_wc_variations_update', 10, 3 );
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'wc_variations_insert_in_bg', __NAMESPACE__ . '\bg_wc_variations_insert', 10, 3 );
			}
		}
	);
}



/**
 * Schedule variation insert
 *
 * @param bool   $variation_processing_allowed Variation insert will be processed.
 * @param int    $post_id Pushed post ID.
 * @param int    $remote_post_id Remote post ID.
 * @param string $signature Generated signature for subscription.
 * @param string $target_url Target url to push to.
 *
 * @return bool
 */
function schedule_variation_insert( $variation_processing_allowed, $post_id, $remote_post_id, $signature, $target_url ) {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task = new \BTM_Task( 'wc_variations_insert_in_bg', [ $post_id, $remote_post_id, $signature, $target_url ], 10 );
		\BTM_Task_Manager::get_instance()->register_task( $btm_task, [] );
	} elseif ( ! wp_next_scheduled( 'dt_wc_variation_insert_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_wc_variation_insert_hook', [ $post_id, $remote_post_id, $signature, $target_url ] );
	}

	return true;
}
/**
 * Schedule variation update
 *
 * @param bool $variation_processing_allowed Variation update will be processed.
 * @param int  $parent_post_id Parent post ID.
 * @param int  $variation_id Updated variation ID.
 *
 * @return bool
 */
function schedule_variation_update( $variation_processing_allowed, $parent_post_id, $variation_id ) {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task     = new \BTM_Task( 'wc_variations_update_in_bg', [ $parent_post_id ], 10 );
		$btm_bulk_arg = new \BTM_Task_Bulk_Argument( [ $variation_id ], -10 );
		\BTM_Task_Manager::get_instance()->register_task( $btm_task, [ $btm_bulk_arg ] );
	} elseif ( ! wp_next_scheduled( 'dt_wc_variation_update_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_wc_variation_update_hook', [ $parent_post_id, $variation_id ] );
	}

	return true;
}

/**
 * Process scheduled insert
 *
 * @param \BTM_Task_Run_Filter_Log  $task_run_filter_log     The logs that callback functions should return.
 * @param mixed[]                   $args           Contains callback params for function.
 * @param \BTM_Task_Bulk_Argument[] $bulk_args              Bulk args, empty for this task.
 *
 * @return \BTM_Task_Run_Filter_Log
 */
function bg_wc_variations_insert( \BTM_Task_Run_Filter_Log $task_run_filter_log, array $args, array $bulk_args ) {
	$post_id        = $args[0];
	$remote_post_id = $args[1];
	$signature      = $args[2];
	$target_url     = $args[3];
	\DT\NbAddon\WC\Hub\push_variations( $post_id, $remote_post_id, $signature, $target_url );
	$message = "initial variation insert for {$post_id} which distributed as {$remote_post_id} in {$target_url}";
	$task_run_filter_log->add_log( $message );

	$task_run_filter_log->set_failed( false );
	return $task_run_filter_log;
}
/**
 * Process scheduled update
 *
 * @param \BTM_Task_Run_Filter_Log  $task_run_filter_log     The logs that callback functions should return.
 * @param mixed[]                   $callback_args           Contains parent post ID.
 * @param \BTM_Task_Bulk_Argument[] $bulk_args              Contains updated variation id(s).
 *
 * @return \BTM_Task_Run_Filter_Log
 */
function bg_wc_variations_update( \BTM_Task_Run_Filter_Log $task_run_filter_log, array $callback_args, array $bulk_args ) {
	$parent_post_id = $callback_args[0];
	$variations     = array();
	foreach ( $bulk_args as $var_arg ) {
		$variations[] = $var_arg->get_callback_arguments()[0];
	}
	\DT\NbAddon\WC\Hub\process_variation_update( $parent_post_id, $variations );
	$message = 'updated variation' . ( count( $variations ) > 1 ? 's' : '' ) . ': ' . implode( ', ', $variations ) . ' in ' . $parent_post_id . ' post';
	$task_run_filter_log->add_log( $message );

	$task_run_filter_log->set_failed( false );
	return $task_run_filter_log;
}


/**
 * Variation insert action callback
 *
 * @param int    $post_id Pushed post ID.
 * @param int    $remote_post_id Remote post ID.
 * @param string $signature Generated signature for subscription.
 * @param string $target_url Target url to push to.
 *
 * @return array
 */
function variation_insert( $post_id, $remote_post_id, $signature, $target_url ) {
	if ( function_exists( '\DT\NbAddon\WC\Hub\push_variations' ) ) {
		return \DT\NbAddon\WC\Hub\push_variations( $post_id, $remote_post_id, $signature, $target_url );
	}
}

/**
 * Variation update action callback
 *
 * @param int $parent_post_id Parent post ID.
 * @param int $variation_id Updated variation ID.
 *
 * @return array
 */
function variation_update( $parent_post_id, $variation_id ) {
	if ( function_exists( '\DT\NbAddon\WC\Hub\process_variation_update' ) ) {
		return \DT\NbAddon\WC\Hub\process_variation_update( $parent_post_id, $variation_id );
	}
}
