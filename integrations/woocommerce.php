<?php

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
			if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'wc_variations_in_bg', __NAMESPACE__ . '\bg_wc_variations_update', 10, 3 );
			}
		}
	);
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
		$btm_task     = new \BTM_Task( 'wc_variations_in_bg', [ $parent_post_id ], 10 );
		$btm_bulk_arg = new \BTM_Task_Bulk_Argument( [ $variation_id ], -10 );
		\BTM_Task_Manager::get_instance()->register_task( $btm_task, [ $btm_bulk_arg ] );
	} elseif ( ! wp_next_scheduled( 'dt_wc_variation_update_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_wc_variation_update_hook', [ $parent_post_id, $variation_id ] );
	}

	return true;
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
