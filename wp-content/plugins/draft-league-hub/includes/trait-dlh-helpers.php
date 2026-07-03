<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Helpers {


	private function manager_select($name, $selected = 0, $placeholder = '', $id = '') {
		$managers = $this->get_managers();
		$id_attr = $id ? ' id="' . esc_attr($id) . '"' : '';
		$html = '<select name="' . esc_attr($name) . '"' . $id_attr . '>';
		$html .= '<option value="0">' . esc_html($placeholder ? $placeholder : __('Choose manager', 'draft-league-hub')) . '</option>';

		foreach ($managers as $manager) {
			$html .= '<option value="' . esc_attr($manager->ID) . '" ' . selected($selected, $manager->ID, false) . '>' . esc_html(get_the_title($manager)) . '</option>';
		}

		$html .= '</select>';
		return $html;
	}


	private function get_managers() {
		return get_posts(
			array(
				'post_type' => 'dlh_manager',
				'post_status' => 'publish',
				'posts_per_page' => 100,
				'orderby' => 'title',
				'order' => 'ASC',
			)
		);
	}


	private function manager_name($manager_id) {
		$manager_id = absint($manager_id);
		if (!$manager_id || 'dlh_manager' !== get_post_type($manager_id)) {
			return __('Unknown manager', 'draft-league-hub');
		}

		$title = get_the_title($manager_id);
		return $title ? $title : __('Unknown manager', 'draft-league-hub');
	}


	private function sidebet_statuses() {
		return array(
			'active' => __('Active', 'draft-league-hub'),
			'settled' => __('Settled', 'draft-league-hub'),
			'void' => __('Void', 'draft-league-hub'),
		);
	}


	private function can_save_post($post_id, $nonce_name, $nonce_action) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return false;
		}

		if (!isset($_POST[$nonce_name]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), $nonce_action)) {
			return false;
		}

		return current_user_can('edit_post', $post_id);
	}


	private function verify_nonce_or_die($action) {
		$nonce = sanitize_text_field(wp_unslash($_POST['dlh_nonce'] ?? ''));
		if (!$nonce || !wp_verify_nonce($nonce, $action)) {
			wp_die(esc_html__('Security check failed. Please go back and try again.', 'draft-league-hub'));
		}
	}


	private function redirect_with_notice($notice) {
		$referer = wp_get_referer();
		$url = $referer ? $referer : home_url('/');
		wp_safe_redirect(add_query_arg('dlh_notice', sanitize_key($notice), $url));
		exit;
	}
}
