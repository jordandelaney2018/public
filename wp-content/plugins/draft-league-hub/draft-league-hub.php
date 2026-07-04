<?php
/**
 * Plugin Name: Draft League Hub
 * Description: A small WordPress hub for FPL Draft leagues: joke news, monthly votes, sidebets, availability polls, and FPL Draft API widgets.
 * Version: 0.1.7
 * Author: Codex
 * Text Domain: draft-league-hub
 */

if (!defined('ABSPATH')) {
	exit;
}

define('DLH_PLUGIN_FILE', __FILE__);
define('DLH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DLH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-post-types.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-assets.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-admin.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-form-handlers.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-shortcodes.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-options.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-votes.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-renderers.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-api.php';
require_once DLH_PLUGIN_DIR . 'includes/trait-dlh-helpers.php';

final class DLH_Plugin {
	use DLH_Post_Types;
	use DLH_Assets;
	use DLH_Admin;
	use DLH_Form_Handlers;
	use DLH_Shortcodes;
	use DLH_Options;
	use DLH_Votes;
	use DLH_Renderers;
	use DLH_Api;
	use DLH_Helpers;

	const VERSION = '0.1.7';
	const OPTION = 'dlh_options';
	const CRON_HOOK = 'dlh_daily_maintenance';

	private static $instance = null;

	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action('init', array($this, 'register_post_types'));
		add_action('init', array($this, 'maybe_upgrade_content'), 30);
		add_action('init', array($this, 'maybe_handle_frontend_posts'), 20);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
		add_action('save_post_dlh_manager', array($this, 'save_manager_meta'));
		add_action('save_post_dlh_sidebet', array($this, 'save_sidebet_meta'));
		add_action('save_post_dlh_hof_entry', array($this, 'save_hall_of_fame_meta'));
		add_action('save_post_dlh_calendar_event', array($this, 'save_calendar_event_meta'));
		add_action(self::CRON_HOOK, array($this, 'daily_maintenance'));

		add_shortcode('dlh_home', array($this, 'shortcode_home'));
		add_shortcode('dlh_news', array($this, 'shortcode_news'));
		add_shortcode('dlh_monthly_votes', array($this, 'shortcode_monthly_votes'));
		add_shortcode('dlh_sidebets', array($this, 'shortcode_sidebets'));
		add_shortcode('dlh_hall_of_fame', array($this, 'shortcode_hall_of_fame'));
		add_shortcode('dlh_calendar', array($this, 'shortcode_calendar'));
		add_shortcode('dlh_stats', array($this, 'shortcode_stats'));
	}

	public static function activate() {
		$plugin = self::instance();
		$plugin->register_post_types();
		$plugin->create_default_pages();
		$plugin->schedule_cron();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook(self::CRON_HOOK);
		flush_rewrite_rules();
	}

	public function daily_maintenance() {
		$this->ensure_current_vote_month();
	}
}

DLH_Plugin::instance();
register_activation_hook(__FILE__, array('DLH_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('DLH_Plugin', 'deactivate'));
