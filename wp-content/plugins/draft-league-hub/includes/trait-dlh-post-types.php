<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Post_Types {


	public function register_post_types() {
		register_post_type(
			'dlh_manager',
			array(
				'labels' => array(
					'name' => __('Managers', 'draft-league-hub'),
					'singular_name' => __('Manager', 'draft-league-hub'),
					'add_new_item' => __('Add Manager', 'draft-league-hub'),
					'edit_item' => __('Edit Manager', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'menu_icon' => 'dashicons-groups',
				'supports' => array('title', 'thumbnail'),
				'capability_type' => 'post',
			)
		);

		register_post_type(
			'dlh_news',
			array(
				'labels' => array(
					'name' => __('League News', 'draft-league-hub'),
					'singular_name' => __('News Story', 'draft-league-hub'),
					'add_new_item' => __('Add News Story', 'draft-league-hub'),
					'edit_item' => __('Edit News Story', 'draft-league-hub'),
				),
				'public' => true,
				'show_ui' => true,
				'show_in_rest' => true,
				'menu_icon' => 'dashicons-megaphone',
				'has_archive' => true,
				'rewrite' => array('slug' => 'league-news'),
				'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'author'),
			)
		);

		register_post_type(
			'dlh_sidebet',
			array(
				'labels' => array(
					'name' => __('Sidebets', 'draft-league-hub'),
					'singular_name' => __('Sidebet', 'draft-league-hub'),
					'add_new_item' => __('Add Sidebet', 'draft-league-hub'),
					'edit_item' => __('Edit Sidebet', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'menu_icon' => 'dashicons-money-alt',
				'supports' => array('title', 'editor', 'author'),
			)
		);

		register_post_type(
			'dlh_hof_entry',
			array(
				'labels' => array(
					'name' => __('Hall of Fame', 'draft-league-hub'),
					'singular_name' => __('Hall of Fame Entry', 'draft-league-hub'),
					'add_new_item' => __('Add Hall of Fame Entry', 'draft-league-hub'),
					'edit_item' => __('Edit Hall of Fame Entry', 'draft-league-hub'),
				),
				'public' => true,
				'show_ui' => true,
				'show_in_rest' => true,
				'menu_icon' => 'dashicons-format-gallery',
				'has_archive' => false,
				'rewrite' => array('slug' => 'hall-of-fame-entry'),
				'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'author'),
			)
		);

		register_post_type(
			'dlh_vote_month',
			array(
				'labels' => array(
					'name' => __('Monthly Votes', 'draft-league-hub'),
					'singular_name' => __('Monthly Vote', 'draft-league-hub'),
					'edit_item' => __('Edit Monthly Vote', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'menu_icon' => 'dashicons-awards',
				'supports' => array('title'),
			)
		);

		register_post_type(
			'dlh_calendar_event',
			array(
				'labels' => array(
					'name' => __('Draft Dates', 'draft-league-hub'),
					'singular_name' => __('Draft Date', 'draft-league-hub'),
					'add_new_item' => __('Add Draft Date', 'draft-league-hub'),
					'edit_item' => __('Edit Draft Date', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'show_in_rest' => true,
				'menu_icon' => 'dashicons-calendar-alt',
				'supports' => array('title', 'editor', 'author'),
			)
		);

		register_post_type(
			'dlh_event_poll',
			array(
				'labels' => array(
					'name' => __('Availability Polls', 'draft-league-hub'),
					'singular_name' => __('Availability Poll', 'draft-league-hub'),
					'edit_item' => __('Edit Availability Poll', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'menu_icon' => 'dashicons-calendar-alt',
				'supports' => array('title', 'editor', 'author'),
			)
		);
	}
}
