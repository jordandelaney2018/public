<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Api {


	private function clear_api_cache($paths = array()) {
		global $wpdb;

		$prefix = '_transient_dlh_api_';
		$cache_keys = get_option('dlh_api_cache_keys', array());
		$cache_keys = is_array($cache_keys) ? $cache_keys : array();
		foreach ((array) $paths as $path) {
			$cache_keys[] = 'dlh_api_' . md5((string) $path);
		}
		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->esc_like($prefix) . '%'
			)
		);

		$deleted = 0;
		foreach ((array) $option_names as $option_name) {
			$cache_keys[] = substr((string) $option_name, strlen('_transient_'));
		}

		foreach (array_unique(array_filter($cache_keys)) as $cache_key) {
			if (delete_transient($cache_key)) {
				$deleted++;
			}
		}
		delete_option('dlh_api_cache_keys');

		return $deleted;
	}


	private function api_get($path) {
		$options = $this->get_options();
		$cache_key = 'dlh_api_' . md5($path);
		$cached = get_transient($cache_key);
		if (false !== $cached) {
			return $cached;
		}

		$url = 'https://draft.premierleague.com' . $path;
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		if (200 !== $code) {
			return new WP_Error('dlh_api_error', sprintf(__('FPL Draft API returned HTTP %d for %s.', 'draft-league-hub'), $code, $path));
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		if (!is_array($data)) {
			return new WP_Error('dlh_api_json', __('FPL Draft API returned unreadable JSON.', 'draft-league-hub'));
		}

		set_transient($cache_key, $data, max(5, absint($options['cache_minutes'])) * MINUTE_IN_SECONDS);
		$cache_keys = get_option('dlh_api_cache_keys', array());
		$cache_keys = is_array($cache_keys) ? $cache_keys : array();
		if (!in_array($cache_key, $cache_keys, true)) {
			$cache_keys[] = $cache_key;
			update_option('dlh_api_cache_keys', $cache_keys, false);
		}
		return $data;
	}
}
