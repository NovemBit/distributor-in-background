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
			add_filter( 'dt_allow_delete_subscriptions', __NAMESPACE__ . '\schedule_delete_subscription', 10, 2 );
			add_filter( 'dt_successfully_distributed_message', __NAMESPACE__ . '\change_successfully_distributed_message', 10, 1 );
			if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'send_notification_in_bg', __NAMESPACE__ . '\bg_redistribute_posts', 10, 3 );
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'push_in_bg', __NAMESPACE__ . '\bg_push_posts', 10, 3 );
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'delete_in_bg', __NAMESPACE__ . '\bg_delete_subscriptions', 10, 3 );
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
function schedule_send_notifications( $allow_send_notifications, $post_id, $priority = 5 ) {
	if ( defined('REST_REQUEST') && REST_REQUEST ) {
		$priority = 15;
	}

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
 * @param int   $priority
 *
 * @return bool
 */
function schedule_push_action( $allow_push, $params, $priority = 5 ) {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task     = new \BTM_Task( 'push_in_bg', [], 1, $priority );
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

	if ( ! empty( $result['results']['external'] ) ) {
		$responses = $result['results']['external'];
		$is_failed = false;
		\DT\NbAddon\DTInBackground\Helpers\add_btm_logs( $params['postId'], $responses, $task_run_filter_log, $is_failed );

		if ( $is_failed ) {
			$task_run_filter_log->set_failed( true );
			$task_run_filter_log->set_bulk_fails( $bulk_args );
		} else {
			$task_run_filter_log->set_failed( false );
		}
	} elseif ( ! empty( $result['results']['internal'] ) ) {
		$status = reset($result['results']['internal'])['status'];

		if ( 'success' === $status ) {
			$task_run_filter_log->add_log( 'pushed post: ' . $params['postId'] );
			$task_run_filter_log->set_failed( false );
		} else {
			$task_run_filter_log->add_log( 'failed to push post: ' . $params['postId'] );
		}
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

/**
 * @param bool $continue Delete subscriptions in background
 * @param array $params Bulk task callback arguments
 *
 * @return bool
 */
function schedule_delete_subscription( bool $continue, array $params ) {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task     = new \BTM_Task( 'delete_in_bg', [], 1, 5 );
		$btm_bulk_arg = new \BTM_Task_Bulk_Argument( $params, -10 );
		\BTM_Task_Manager::get_instance()->register_task( $btm_task, [ $btm_bulk_arg ] );

		return false;
	}

	return true;
}

/**
 * @param \BTM_Task_Run_Filter_Log $task_run_filter_log
 * @param array $callback_args
 * @param array $bulk_args
 *
 * @return \BTM_Task_Run_Filter_Log
 */
function bg_delete_subscriptions( \BTM_Task_Run_Filter_Log $task_run_filter_log, array $callback_args, array $bulk_args ) {
	$post_id            = $bulk_args[0]->get_callback_arguments()['post_id'];
	$original_source_id = $bulk_args[0]->get_callback_arguments()['original_source_id'];
	$original_post_id   = $bulk_args[0]->get_callback_arguments()['original_post_id'];
	$subscriptions      = $bulk_args[0]->get_callback_arguments()['subscriptions'];
	$result             = [];

	if ( ! empty( $original_source_id ) && ! empty( $original_post_id ) ) {
		// This case happens if a post is deleted that is subscribing to a remote post
		$connection = \Distributor\ExternalConnection::instantiate( $original_source_id );

		if ( ! is_wp_error( $connection ) ) {
			$response = \Distributor\Subscriptions\delete_remote_subscription( $connection, $original_post_id, $post_id );

			if ( ! is_wp_error( $response ) ) {
				$response_code = wp_remote_retrieve_response_code( $response );
				$body          = wp_remote_retrieve_body( $response );

				$result[$post_id]['response']['code'] = $response_code;
				$result[$post_id]['response']['body'] = $body;
			} else {
				$result[$post_id]['response'] = $response;
			}
		} else {
			$result[$post_id]['response'] = $connection;
		}
	} elseif ( ! empty( $subscriptions ) ) {
		// This case happens if a post is deleted that is being subscribed to

		foreach ( $subscriptions as $subscription_key => $subscription_id ) {
			$signature      = get_post_meta( $subscription_id, 'dt_subscription_signature', true );
			$remote_post_id = get_post_meta( $subscription_id, 'dt_subscription_remote_post_id', true );
			$target_url     = get_post_meta( $subscription_id, 'dt_subscription_target_url', true );

			$result[$subscription_key]['target_url'] = $target_url;

			wp_delete_post( $subscription_id, true );

			if ( empty( $signature ) || empty( $remote_post_id ) || empty( $target_url ) ) {
				continue;
			}

			// We need to ensure any remote post is unlinked to this post
			$response = wp_remote_post(
				untrailingslashit( $target_url ) . '/wp/v2/dt_subscription/receive',
				[
					'timeout'  => 45,
					'body'     => [
						'post_id'          => $remote_post_id,
						'signature'        => $signature,
						'original_deleted' => true,
					],
				]
			);

			if ( ! is_wp_error( $response ) ) {
				$response_code = wp_remote_retrieve_response_code( $response );
				$body          = wp_remote_retrieve_body( $response );

				$result[$subscription_key]['response']['code'] = $response_code;
				$result[$subscription_key]['response']['body'] = $body;
			} else {
				$result[$subscription_key]['response'] = $response;
			}
		}
	}

	$is_failed = false;
	\DT\NbAddon\DTInBackground\Helpers\add_btm_logs( $post_id, $result, $task_run_filter_log, $is_failed );

	if ( $is_failed ) {
		$task_run_filter_log->set_failed( true );
		$task_run_filter_log->set_bulk_fails( $bulk_args );
	} else {
		$task_run_filter_log->set_failed( false );
	}

	return $task_run_filter_log;
}
