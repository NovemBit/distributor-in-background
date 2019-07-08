<?php

namespace DistributorInBackground\Hooks;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_filter( 'dt_send_notification_allow_in_background', __NAMESPACE__ . '\schedule_send_notifications', 10, 2 );
			add_filter( 'dt_push_allow_in_background', __NAMESPACE__ . '\schedule_push_action', 10, 2 );
			add_filter( 'dt_clone_fix_allow_in_background', __NAMESPACE__ . '\schedule_clone_fix', 10, 3 );
			add_filter( 'dt_successfully_distributed_message', __NAMESPACE__ . '\change_successfully_distributed_message', 10, 1 );
			add_action( 'dt_redistribute_posts_hook', __NAMESPACE__ . '\redistribute_posts' );
			add_action( 'dt_push_posts_hook', __NAMESPACE__ . '\push_action' );
			add_action( 'dt_clone_fix_hook', __NAMESPACE__ . '\clone_fix' );
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
	// todo implement via 'wp-task-manager'
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return false;
	}

	update_post_meta( $post_id, 'dt_redistribute_post', 'yes' );
	if ( ! wp_next_scheduled( 'dt_redistribute_posts_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_redistribute_posts_hook' );
	}

	return true;
}

/**
 * Schedule post push
 *
 * @param bool $push_in_bg
 * @param array $params
 *
 * @return bool
 */
function schedule_push_action( $push_in_bg, $params ) {
	// todo implement via 'wp-task-manager'
	if ( ! wp_next_scheduled( 'dt_push_posts_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_push_posts_hook' );
	}

	return true;
}

/**
 * @param bool $clone_fix_in_bg
 * @param array $posts
 * @param string $connection_id
 *
 * @return bool
 */
function schedule_clone_fix( $clone_fix_in_bg, $posts, $connection_id ) {
	// todo implement via 'wp-task-manager'
	if ( ! wp_next_scheduled( 'dt_clone_fix_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_clone_fix_hook' );
	}

	return true;
}

/**
 * Perform posts redistribution
 */
function redistribute_posts() {
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
 * @param array $params
 */
function clone_fix( $params ) {
	if( function_exists( '\DT\NbAddon\CloneFix\Hub\push_post_data' ) ) {
		\DT\NbAddon\CloneFix\Hub\push_post_data( $params['posts'], $params['connection_id'] );
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
