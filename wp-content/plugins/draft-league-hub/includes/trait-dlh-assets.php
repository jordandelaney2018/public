<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Assets {


	public function enqueue_assets() {
		wp_enqueue_style(
			'dlh-styles',
			DLH_PLUGIN_URL . 'assets/dlh.css',
			array(),
			self::VERSION
		);
	}


	public function enqueue_admin_assets($hook) {
		if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
			return;
		}

		$screen = get_current_screen();
		if (!$screen || 'dlh_hof_entry' !== $screen->post_type) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'dlh-admin-styles',
			DLH_PLUGIN_URL . 'assets/admin.css',
			array(),
			self::VERSION
		);
		wp_enqueue_script(
			'dlh-admin-hall-of-fame',
			DLH_PLUGIN_URL . 'assets/admin-hall-of-fame.js',
			array('jquery'),
			self::VERSION,
			true
		);
	}
}
