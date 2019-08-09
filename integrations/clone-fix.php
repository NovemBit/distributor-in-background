<?php
/**
 * Handle Clone Fix integration
 *
 * @package distributor-in-background
 */

namespace DT\NbAddon\DTInBackground\CloneFix;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_filter( 'dt_allow_clone_fix', __NAMESPACE__ . '\schedule_clone_fix', 10, 3 );
			add_action( 'dt_clone_fix_hook', __NAMESPACE__ . '\clone_fix', 10, 2 );
			if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'clone_fix_in_bg', __NAMESPACE__ . '\bg_clone_fix', 10, 3 );
			}
		}
	);
}

/**
 * Schedule clone fix
 *
 * @param bool   $clone_fix_in_bg Clone fix will be processed.
 * @param array  $posts Contains post IDs to be fixed.
 * @param string $connection_id Connection ID to be fixed.
 *
 * @return bool
 */
function schedule_clone_fix( $clone_fix_in_bg, $posts, $connection_id ) {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task     = new \BTM_Task( 'clone_fix_in_bg', [ $connection_id ], 10 );
		$btm_bulk_arg = new \BTM_Task_Bulk_Argument( is_array( $posts ) ? $posts : [ $posts ], -10 );
		\BTM_Task_Manager::get_instance()->register_task_bulk( $btm_task, $btm_bulk_arg );
	} elseif ( ! wp_next_scheduled( 'dt_clone_fix_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_clone_fix_hook', [ $posts, $connection_id ] );
	}

	return true;
}

/**
 * Clone fix action callback
 *
 * @param array  $posts Post IDs to be fixed.
 * @param string $connection_id Connection ID to be fixed.
 *
 * @return array
 */
function clone_fix( $posts, $connection_id ) {
	if ( function_exists( '\DT\NbAddon\CloneFix\Hub\push_post_data' ) ) {
		return \DT\NbAddon\CloneFix\Hub\push_post_data( $posts, $connection_id );
	}
}

/**
 * Process Clone Fix
 *
 * @param \BTM_Task_Run_Filter_Log  $task_run_filter_log    The logs that callback functions should return
 * @param mixed[]                   $callback_args          Empty
 * @param \BTM_Task_Bulk_Argument[] $bulk_args              Contains posts, needs to be fixed, and connection id
 *
 * @return \BTM_Task_Run_Filter_Log
 */
function bg_clone_fix( \BTM_Task_Run_Filter_Log $task_run_filter_log, array $callback_args, array $bulk_args ) {
	$connection_id = $callback_args[0];
	$posts         = array();
	foreach ( $bulk_args as $post_arg ) {
		$posts[ $post_arg->get_callback_arguments()[0] ] = $post_arg;
	}

	$status = clone_fix( array_keys( $posts ), $connection_id );
	if ( ! $status ) {
		$task_run_filter_log->set_bulk_fails( $bulk_args );
		$task_run_filter_log->add_log( 'failed to fix posts in connection: ' . $connection_id );
		return $task_run_filter_log;
	}

	$is_failed = false;
	if ( isset( $status['data'] ) && is_array( $status['data'] ) ) {
		foreach ( $status['data'] as $post_id => $result ) {
			if ( 'success' === $result['status'] ) {
				$task_run_filter_log->add_log( 'Fixed post having id: ' . $post_id );
			} else {
				$task_run_filter_log->add_log( 'Failed to fix post having id: ' . $post_id );
				$is_failed = true;
			}
		}
	}

	if ( false === $is_failed ) {
		$task_run_filter_log->add_log( 'fixed posts in connection: ' . $connection_id );

		$task_run_filter_log->set_failed( false );
	}

	return $task_run_filter_log;
}
