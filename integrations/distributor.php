<?php
/**
 * Handle core Distributor integration
 *
 * @package distributor-in-background
 */

namespace DT\NbAddon\DTInBackground\Distributor;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_filter( 'dt_allow_send_notifications', __NAMESPACE__ . '\schedule_send_notifications', 10, 2 );
			add_filter( 'dt_allow_push', __NAMESPACE__ . '\schedule_push_action', 10, 2 );
			add_filter( 'dt_successfully_distributed_message', __NAMESPACE__ . '\change_successfully_distributed_message', 10, 1 );
			if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'send_notification_in_bg', __NAMESPACE__ . '\bg_redistribute_posts', 10, 3 );
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'push_in_bg', __NAMESPACE__ . '\bg_push_posts', 10, 3 );
			}
		}
	);
}

/**
 * Schedule post redistribution
 *
 * @param bool $allow_send_notifications Send notification in background, default false.
 * @param int  $post_id Post id.
 * @param int  $priority
 *
 * @return bool
 */
function schedule_send_notifications( $allow_send_notifications, $post_id, $priority = 15 ) {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task     = new \BTM_Task( 'send_notification_in_bg', [], 1, $priority );
		$btm_bulk_arg = new \BTM_Task_Bulk_Argument( [ $post_id ], -10 );
		\BTM_Task_Manager::get_instance()->register_task( $btm_task, [ $btm_bulk_arg ] );

		return false;
	}

	return true;
}

/**
 * Process scheduled redistribution
 *
 * @param \BTM_Task_Run_Filter_Log  $task_run_filter_log     The logs that callback functions should return
 * @param mixed[]                   $callback_args                            Empty
 * @param \BTM_Task_Bulk_Argument[] $bulk_args              Contains post ids
 *
 * @return \BTM_Task_Run_Filter_Log
 */
function bg_redistribute_posts( \BTM_Task_Run_Filter_Log $task_run_filter_log, array $callback_args, array $bulk_args ) {
	$post_id   = $bulk_args[0]->get_callback_arguments()[0];
	$responses = redistribute_posts( $post_id );
	$is_failed = false;

	\DT\NbAddon\DTInBackground\Helpers\add_btm_logs( $post_id, $responses, $task_run_filter_log, $is_failed );

	if ( $is_failed ) {
		$task_run_filter_log->set_failed( true );
		$task_run_filter_log->set_bulk_fails( $bulk_args );
	} else {
		$task_run_filter_log->set_failed( false );
	}

	return $task_run_filter_log;
}

/**
 * Schedule post push
 *
 * @param bool  $allow_push Push will be processed.
 * @param array $params Array containing callback params.
 *
 * @return bool
 */
function schedule_push_action( $allow_push, $params ) {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task     = new \BTM_Task( 'push_in_bg', [], 1 );
		$btm_bulk_arg = new \BTM_Task_Bulk_Argument( [ $params ], -10 );
		\BTM_Task_Manager::get_instance()->register_task( $btm_task, [ $btm_bulk_arg ] );

		return false;
	}

	return true;
}

/**
 * Process push
 *
 * @param \BTM_Task_Run_Filter_Log  $task_run_filter_log     The logs that callback functions should return
 * @param mixed[]                   $callback_args                            Empty
 * @param \BTM_Task_Bulk_Argument[] $bulk_args              Contains $_POST data
 *
 * @return \BTM_Task_Run_Filter_Log
 */
function bg_push_posts( \BTM_Task_Run_Filter_Log $task_run_filter_log, array $callback_args, array $bulk_args ) {
	$params = $bulk_args[0]->get_callback_arguments()[0];

	$result = push_action( $params );
	$status = '';
	if ( ! empty( $result['results']['external'] ) ) {
		$status = reset($result['results']['external'])['status'];
	} elseif ( ! empty( $result['results']['internal'] ) ) {
		$status = reset($result['results']['internal'])['status'];
	}

	if ( 'success' === $status ) {
		$task_run_filter_log->add_log( 'pushed post: ' . $params['postId'] );
		$task_run_filter_log->set_failed( false );
	} else {
		$task_run_filter_log->add_log( 'failed to push post: ' . $params['postId'] );
	}

	return $task_run_filter_log;
}

/**
 * Perform posts redistribution
 *
 * @param int $post_id Post ID to be redistributed.
 *
 * @return array|void
 */
function redistribute_posts( $post_id ) {
	return \Distributor\Subscriptions\send_notifications( $post_id );
}

/**
 * Scheduled push action callback
 *
 * @param array $params Callback parameters.
 *
 * @return array
 */
function push_action( $params ) {
	return \Distributor\PushUI\push( $params );
}

/**
 * Change success message as instead of immediately distribution post scheduled to be distributed
 *
 * @param string $message Message to send.
 *
 * @return string
 */
function change_successfully_distributed_message( $message ) {
	return esc_html__( 'Post scheduled.', 'distributor' );
}
