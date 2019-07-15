<?php

namespace DT\NbAddon\DTInBackground\Hooks;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_filter( 'dt_allow_send_notifications', __NAMESPACE__ . '\schedule_send_notifications', 10, 2 );
			add_filter( 'dt_allow_push', __NAMESPACE__ . '\schedule_push_action', 10, 2 );
			add_filter( 'dt_allow_clone_fix', __NAMESPACE__ . '\schedule_clone_fix', 10, 3 );
			add_filter( 'dt_successfully_distributed_message', __NAMESPACE__ . '\change_successfully_distributed_message', 10, 1 );
			add_action( 'dt_redistribute_posts_hook', __NAMESPACE__ . '\redistribute_posts', 10, 1 );
			add_action( 'dt_push_posts_hook', __NAMESPACE__ . '\push_action', 10, 1 );
			add_action( 'dt_clone_fix_hook', __NAMESPACE__ . '\clone_fix', 10, 2 );
		}
	);
}

/**
 * Schedule post redistribution
 *
 * @param bool $send_notification_in_bg Send notification in background, default false.
 * @param int  $post_id Post id.
 *
 * @return bool
 */
function schedule_send_notifications( $send_notification_in_bg, $post_id ) {
	// todo check 'wp-task-manager' implementation
	if ( is_btm_active() ) {
		$btm_task         = new \BTM_Task( 'send_notification_in_bg', [ $post_id ], 1 );
		$btm_task_manager = BTM_Task_Manager::get_instance()->register_task( $btm_task, [ $post_id ] );
	} else {
		update_post_meta( $post_id, 'dt_redistribute_post', 'yes' );
		if ( ! wp_next_scheduled( 'dt_redistribute_posts_hook' ) ) {
			wp_schedule_single_event( time(), 'dt_redistribute_posts_hook', [ $post_id ] );
		}
	}

	return false;
}

/**
 * Schedule post push
 *
 * @param bool  $push_in_bg
 * @param array $params
 *
 * @return bool
 */
function schedule_push_action( $push_in_bg, $params ) {
	// todo check 'wp-task-manager' implementation
	if ( is_btm_active() ) {
		$btm_task         = new \BTM_Task( 'send_notification_in_bg', [ $params ], 1 );
		$btm_task_manager = BTM_Task_Manager::get_instance()->register_task( $btm_task, [ $params ] );
	} elseif ( ! wp_next_scheduled( 'dt_push_posts_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_push_posts_hook', [ $params ] );
	}

	return false;
}

/**
 * @param bool   $clone_fix_in_bg
 * @param array  $posts
 * @param string $connection_id
 *
 * @return bool
 */
function schedule_clone_fix( $clone_fix_in_bg, $posts, $connection_id ) {
	// todo check 'wp-task-manager' implementation
	if ( is_btm_active() ) {
		$btm_task         = new \BTM_Task( 'send_notification_in_bg', [ $posts, $connection_id ], 1 );
		$btm_task_manager = BTM_Task_Manager::get_instance()->register_task( $btm_task, [ $posts, $connection_id ] );
	} elseif ( ! wp_next_scheduled( 'dt_clone_fix_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_clone_fix_hook', [ $posts, $connection_id ] );
	}

	return true;
}

/**
 * Perform posts redistribution
 */
function redistribute_posts( $post_id ) {
	$query = new \WP_Query(
		array(
			'post_type'      => get_distributable_custom_post_types(),
			'post_status'    => array( 'publish', 'draft', 'trash' ),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'posts_per_page' => 20,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'dt_redistribute_post',
					'compare' => 'EXISTS',
				),
			),
		)
	);
	$posts = $query->posts;
	if ( ! empty( $posts ) ) {
		$post_ids = array();
		foreach ( $posts as $post ) {
			$post_ids[] = $post->ID;
		}
		$count = count( $post_ids );
		foreach ( $posts as $post ) {
			\Distributor\Subscriptions\send_notifications( $post->ID );
			delete_post_meta( $post->ID, 'dt_redistribute_post' );

			for ( $i = 0; $i < $count; $i++ ) {
				if ( isset( $post_ids[ $i ] ) && $post_ids[ $i ] == $post->ID ) {
					unset( $post_ids[ $i ] );
					update_option( 'dt_redistributing_posts', $post_ids, false );
				}
			}
		}
		if ( $query->found_posts > $query->post_count ) {
			wp_schedule_single_event( time(), 'dt_redistribute_posts_hook' );
		}
	}

}

/**
 * Scheduled push action callback
 *
 * @param array $params
 */
function push_action( $params ) {
	\Distributor\PushUI\push( $params );
}

/**
 * Clone fix action callback
 *
 * @param array  $posts
 * @param string $connection_id
 */
function clone_fix( $posts, $connection_id ) {
	if ( function_exists( '\DT\NbAddon\CloneFix\Hub\push_post_data' ) ) {
		\DT\NbAddon\CloneFix\Hub\push_post_data( $posts, $connection_id );
	}
}

/**
 * Change success message as instead of immediately distribution post scheduled to be distributed
 *
 * @param string $message
 *
 * @return string
 */
function change_successfully_distributed_message( $message ) {
	return esc_html__( 'Post scheduled.', 'distributor' );
}

/**
 * Helper function to determine whether 'wp task manager' plug-in is active or not
 *
 * @return bool
 */
function is_btm_active() {
	return class_exists( 'BTM_Plugin' );
}
