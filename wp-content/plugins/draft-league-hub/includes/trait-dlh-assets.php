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
}
