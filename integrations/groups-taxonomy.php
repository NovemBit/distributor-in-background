<?php

namespace DT\NbAddon\DTInBackground\GroupsTaxonomy;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_action( 'dt_groups_taxonomy_metabox_saved', __NAMESPACE__ . '\schedule_push_groups', 10, 2 );
			if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'push_groups_in_bg', __NAMESPACE__ . '\bg_push_groups', 10, 3 );
			}
		}
	);
}

/**
 * Schedule Groups Taxonomies distribution
 *
 * @param int  $post_id Post id
 * @param bool $are_groups_updated Whether groups updated or not
 */
function schedule_push_groups( $post_id, $are_groups_updated ) {
	if( true === $are_groups_updated ) {
		if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
			$btm_task = new \BTM_Task( 'push_groups_in_bg', [], 10 );
			\BTM_Task_Manager::get_instance()->register_task( $btm_task, [] );
		}
	}
}

/**
 * Schedule the next pack of posts to be distributed
 */
function schedule_next_pack() {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task = new \BTM_Task( 'push_groups_in_bg', [], 10 );
		\BTM_Task_Manager::get_instance()->register_task( $btm_task, [] );
	}
}


/**
 * Process scheduled redistribution
 *
 * @param \BTM_Task_Run_Filter_Log $task_run_filter_log The logs that callback functions should return
 * @param array $callback_args                          Empty
 * @param array $bulk_args                              Empty
 *
 * @return \BTM_Task_Run_Filter_Log
 */
function bg_push_groups( \BTM_Task_Run_Filter_Log $task_run_filter_log, array $callback_args, array $bulk_args ) {

	$pushed_post_ids = dt_push_groups();

	$task_run_filter_log->add_log( 'Groups taxonomy pushed' );
	$task_run_filter_log->add_log( implode( $pushed_post_ids, ', ' ) );

	$task_run_filter_log->set_failed( false );

	return $task_run_filter_log;
}

/**
 * Perform scheduled push groups
 */
function dt_push_groups() {
	$found_posts = 0;
	$count       = 0;
	$exists_post = true;
	$post_ids    = [];

	while ( $count++ < 5 && $exists_post ) {
		$query = new \WP_Query(
			array(
				'post_type'      => \DT\NbAddon\Brandlight\Utils\get_distributable_custom_post_types(),
				'posts_per_page' => 1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'dt_connection_groups_pushing',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$post        = $query->post;
		$found_posts = $query->found_posts;

		if ( $post ) {
			$post_ids[]     = $post->ID;
			$groups_pushing = get_post_meta( $post->ID, 'dt_connection_groups_pushing', true ) ?: array();
			delete_post_meta( $post->ID, 'dt_connection_groups_pushing' );

			if ( empty( $groups_pushing ) ) {
				continue;
			}

			$groups_pushed    = get_post_meta( $post->ID, 'dt_connection_groups_pushed', true ) ?: array();
			$succeeded_groups = array();

			foreach ( $groups_pushing as $key => $group ) {
				$push_connections = \DT\NbAddon\GroupsTaxonomy\Hooks\get_connections( $group );

				if ( ! empty( $push_connections )) {
					$connection_map = get_post_meta( $post->ID, 'dt_connection_map', true );

					foreach ( $push_connections as $con ) {
						if ( empty( $connection_map ) || ! isset( $connection_map['external'] ) || ! in_array( $con['id'], array_keys( $connection_map['external'] ) ) ) { //phpcs:ignore
							\DT\NbAddon\GroupsTaxonomy\Hooks\push_connection( $con, $post );
						}
					}
				}

				unset( $groups_pushing[ $key ] );
				$succeeded_groups[] = $group;
			}

			if ( $added_groups = array_diff( $succeeded_groups,  $groups_pushed ) ) {
				$groups_pushed = array_merge( $groups_pushed, $added_groups );
				update_post_meta( $post->ID, 'dt_connection_groups_pushed', $groups_pushed );
			}

			if ( ! empty( $groups_pushing ) ) {
				update_post_meta( $post->ID, 'dt_connection_groups_pushing_failed', $groups_pushing );
			}
		} else {
			$exists_post = false;
		}
	}

	// Re-schedule a new event when there are still others to be distributed.
	if ( $found_posts > 1 ) {
		schedule_next_pack();
	}

	return $post_ids;
}
