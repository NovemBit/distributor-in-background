<?php

namespace DT\NbAddon\DTInBackground\GroupsTaxonomy;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_action( 'dt_groups_taxonomy_metabox_saved', __NAMESPACE__ . '\schedule_push_groups', 10, 0 );
			add_action( 'dt_push_groups_hook', __NAMESPACE__ . '\dt_push_groups' );
			if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
				add_filter( \BTM_Plugin_Options::get_instance()->get_task_filter_name_prefix() . 'push_groups_in_bg', __NAMESPACE__ . '\bg_push_groups', 10, 3 );
			}
		}
	);
}

/**
 * Schedule Groups Taxonomies distribution
 */
function schedule_push_groups() {
	if ( \DT\NbAddon\DTInBackground\Helpers\is_btm_active() ) {
		$btm_task = new \BTM_Task( 'push_groups_in_bg', [], 10 );
		\BTM_Task_Manager::get_instance()->register_task( $btm_task, [] );
	} elseif ( ! wp_next_scheduled( 'dt_push_groups_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_push_groups_hook', [] );
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

	dt_push_groups();

	$task_run_filter_log->add_log( 'Groups taxonomy pushed' );

	$task_run_filter_log->set_failed( false );

	return $task_run_filter_log;
}

/**
 * Perform scheduled push groups
 */
function dt_push_groups() {
	$query = new \WP_Query(
		array(
			'post_type'      => \DT\NbAddon\GroupsTaxonomy\Utils\get_distributable_custom_post_types(),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'posts_per_page' => 20,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'dt_connection_groups_pushing',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	$all_posts = $query->posts;
	if ( ! empty( $all_posts ) ) {
		foreach ( $all_posts as $post ) {
			$connection_map = get_post_meta( $post->ID, 'dt_connection_groups_pushing', true );
			if ( empty( $connection_map ) ) {
				delete_post_meta( $post->ID, 'dt_connection_groups_pushing' );
				continue;
			} elseif ( ! is_array( $connection_map ) ) {
				$connection_map = array( $connection_map );
			}
			$successed_groups = get_post_meta( $post->ID, 'dt_connection_groups_pushed', true );
			if ( empty( $successed_groups ) || null === $successed_groups ) {
				$successed_groups = array();
			}
			foreach ( $connection_map as $group ) {

				$index                = get_term_by( 'slug', $group, 'dt_ext_connection_group' )->term_id;
				$push_connections = \DT\NbAddon\GroupsTaxonomy\Hooks\get_connections( $group );
				if ( empty( $push_connections ) ) {
					$key = array_search( $group, $connection_map, true );
					if ( ! in_array( $group, $successed_groups, true ) ) {
						$successed_groups[] = $group;
						update_post_meta( $post->ID, 'dt_connection_groups_pushed', $successed_groups );
					}
					if ( false !== $key || null !== $key ) {
						unset( $connection_map[ $key ] );
					}
					continue;
				}
				$pushed_connections_map = get_post_meta( $post->ID, 'dt_connection_map', true );

				foreach ( $push_connections as $con ) {
					if ( empty( $pushed_connections_map ) || ! isset( $pushed_connections_map['external'] ) || ! in_array( $con['id'], array_keys( $pushed_connections_map['external'] ), true ) ) {
						\DT\NbAddon\GroupsTaxonomy\Hooks\push_connection( $con, $post );
					}
				}

				$key = array_search( $group, $connection_map, true );
				if ( ! in_array( $group, $successed_groups, true ) ) {
					$successed_groups[] = $group;
					update_post_meta( $post->ID, 'dt_connection_groups_pushed', $successed_groups );
				}
				if ( false !== $key || null !== $key ) {
					unset( $connection_map[ $key ] );
				}
			}
			if ( empty( $connection_map ) ) {
				delete_post_meta( $post->ID, 'dt_connection_groups_pushing' );
			} else {
				update_post_meta( $post->ID, 'dt_connection_groups_pushing', $connection_map );
			}
		}
	}

	// Re-schedule a new event when there are still others to be distributed.
	if ( $query->found_posts > $query->post_count ) {
		schedule_push_groups();
	}
}
