<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Api {


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
		return $data;
	}
}
