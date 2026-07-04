<?php
/**
 * Draft League Theme setup.
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support('title-tag');
		add_theme_support('post-thumbnails');
		add_theme_support('responsive-embeds');
		add_theme_support('html5', array('comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets'));

		register_nav_menus(
			array(
				'primary' => __('Primary Menu', 'draft-league-theme'),
				'footer' => __('Footer Menu', 'draft-league-theme'),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style('draft-league-theme', get_stylesheet_uri(), array(), '0.1.1');
		wp_enqueue_script(
			'draft-league-theme-navigation',
			get_stylesheet_directory_uri() . '/assets/navigation.js',
			array(),
			'0.1.1',
			true
		);
	}
);

add_filter(
	'template_include',
	function ($template) {
		if (is_page() && !is_front_page()) {
			$page_template = get_stylesheet_directory() . '/page.php';
			if (file_exists($page_template)) {
				return $page_template;
			}
		}

		return $template;
	},
	20
);

function draft_league_theme_site_name() {
	$name = get_bloginfo('name');
	return $name ? $name : __('Draft League', 'draft-league-theme');
}

function draft_league_theme_get_menu_items($location, $fallback_menu = '') {
	$locations = get_nav_menu_locations();
	$menu_id = absint($locations[$location] ?? 0);

	if (!$menu_id && $fallback_menu) {
		$menu = wp_get_nav_menu_object($fallback_menu);
		$menu_id = $menu ? absint($menu->term_id) : 0;
	}

	if (!$menu_id) {
		return array();
	}

	$items = wp_get_nav_menu_items($menu_id);
	return is_array($items) ? $items : array();
}

function draft_league_theme_nav_menu($location, $fallback_menu = '') {
	$items = draft_league_theme_get_menu_items($location, $fallback_menu);

	if (empty($items) && 'primary' === $location) {
		wp_page_menu(array('show_home' => true));
		return;
	}

	if (empty($items)) {
		return;
	}

	echo '<ul>';
	foreach ($items as $item) {
		if ((int) $item->menu_item_parent !== 0) {
			continue;
		}

		$classes = implode(' ', array_filter((array) $item->classes));
		echo '<li class="' . esc_attr($classes) . '">';
		echo '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
		echo '</li>';
	}
	echo '</ul>';
}

function draft_league_theme_page_has_hub_shortcode($post = null) {
	$post = get_post($post);
	if (!$post) {
		return false;
	}

	foreach (array('dlh_home', 'dlh_news', 'dlh_monthly_votes', 'dlh_sidebets', 'dlh_hall_of_fame', 'dlh_calendar', 'dlh_stats') as $shortcode) {
		if (has_shortcode($post->post_content, $shortcode)) {
			return true;
		}
	}

	return false;
}
