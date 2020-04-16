<?php
/**
 * Helper functions
 *
 * @package distributor-in-background
 */

namespace DT\NbAddon\DTInBackground\Helpers;

/**
 * Helper function to determine whether 'wp task manager' plug-in is active or not
 *
 * @return bool
 */
function is_btm_active() {
	return defined( 'BTM_PLUGIN_ACTIVE' ) && true === BTM_PLUGIN_ACTIVE;
}

/**
 * @param int $post_id
 * @param array $responses
 * @param \BTM_Task_Run_Filter_Log $task_run_filter_log
 * @param bool $is_failed
 */
function add_btm_logs( int $post_id, array $responses, \BTM_Task_Run_Filter_Log $task_run_filter_log, bool &$is_failed ) {
	$is_failed          = false;
	$http_success_codes = [
		'ok'      => 200,
		'created' => 201
	];

	foreach ( $responses as $response ) {
		if ( isset( $response['response'] ) && isset( $response['target_url'] ) ) {
			if ( !is_wp_error( $response['response'] ) ) {
				$target_url    = $response['target_url'];
				$response_code = $response['response']['code'] ?? null;
				$response_body = $response['response']['body'] ?? null;
				$response_body = $response_body ? json_decode( $response_body, true ) : null;

				if ( $response_code && $target_url ) {
					if ( in_array( $response_code, $http_success_codes ) ) {
						$message = "target: {$target_url}, response code: {$response_code}, post: {$post_id}";

						if ( $response_body ) {
							foreach ( $response_body as $field => $value ) {
								$value   = is_array( $value ) ? json_encode( $value ) : $value;
								$message .= ", {$field}: {$value}";
							}
						}

						$task_run_filter_log->add_log( $message );
					} else {
						$is_failed = true;

						if ( isset( $response_body['message'] ) ) {
							$task_run_filter_log->add_log( "target: {$target_url}, response code: {$response_code}, post: {$post_id}, message: {$response_body['message']}" );
						} else {
							$task_run_filter_log->add_log( "target: {$target_url}, response code: {$response_code}, post: {$post_id}" );
						}
					}
				} else {
					$is_failed = true;
					$task_run_filter_log->add_log( "message: Uncaught error" );
				}
			} else {
				$is_failed  = true;
				$target_url = $response['target_url'];
				$message    = $response['response']->get_error_message();
				$task_run_filter_log->add_log( "target: {$target_url}, post: {$post_id}, message: {$message}" );
			}
		} else {
			$is_failed = true;
			$task_run_filter_log->add_log( "message: Uncaught error" );
		}
	}
}
