<?php

namespace DistributorInBackground\Hooks;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_filter( 'dt_send_notification_allow_in_background', __NAMESPACE__ . '\schedule_send_notifications', 10, 3 );
			add_action( 'dt_redistribute_posts_hook', __NAMESPACE__ . '\redistribute_posts' );
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
		remove_filter( 'dt_send_notification_allow_in_background', __NAMESPACE__ . '\schedule_send_notifications', 10 );
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
		add_filter( 'dt_send_notification_allow_in_background', __NAMESPACE__ . '\schedule_send_notifications', 10, 3 );
	}

}
