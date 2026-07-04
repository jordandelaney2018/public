<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Votes {


	private function parse_default_questions($raw) {
		$lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
		$questions = array();
		$seen = array();

		foreach ($lines as $line) {
			$parts = array_map('trim', explode('|', $line));
			$label = sanitize_text_field($parts[0] ?? '');
			$type = $this->normalize_question_type($parts[1] ?? 'text');
			if ('' === $label) {
				continue;
			}

			$key = sanitize_key(sanitize_title($label));
			if (isset($seen[$key])) {
				$seen[$key]++;
				$key .= '-' . $seen[$key];
			} else {
				$seen[$key] = 1;
			}

			$questions[] = array(
				'key' => $key,
				'label' => $label,
				'type' => $type,
			);
		}

		return $questions;
	}


	private function normalize_question_type($type) {
		$type = sanitize_key($type);
		return in_array($type, array('manager', 'text'), true) ? $type : 'text';
	}


	private function ensure_current_vote_month() {
		$timezone = wp_timezone();
		$now = new DateTime('now', $timezone);
		$month_key = $now->format('Y-m');
		$close = $this->vote_month_close_datetime($now);

		$existing = get_posts(
			array(
				'post_type' => 'dlh_vote_month',
				'post_status' => 'any',
				'posts_per_page' => 1,
				'fields' => 'ids',
				'meta_key' => 'dlh_month',
				'meta_value' => $month_key,
			)
		);

		if (!empty($existing)) {
			$post_id = absint($existing[0]);
			$current_close = get_post_meta($post_id, 'dlh_open_until', true);
			if ($current_close !== $close->format('Y-m-d H:i:s')) {
				update_post_meta($post_id, 'dlh_open_until', $close->format('Y-m-d H:i:s'));
			}

			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => 'dlh_vote_month',
				'post_status' => 'publish',
				'post_title' => $now->format('F Y') . ' Votes',
			)
		);

		if (!is_wp_error($post_id) && $post_id) {
			$options = $this->get_options();
			update_post_meta($post_id, 'dlh_month', $month_key);
			update_post_meta($post_id, 'dlh_open_until', $close->format('Y-m-d H:i:s'));
			update_post_meta($post_id, 'dlh_questions', $this->parse_default_questions($options['default_questions']));
			update_post_meta($post_id, 'dlh_votes', array());
		}

		return absint($post_id);
	}


	private function vote_month_close_datetime(DateTime $date) {
		$close = clone $date;
		$close->modify('first day of this month')->setTime(23, 59, 59);

		return $close;
	}


	private function is_vote_closed($vote_id) {
		$close = get_post_meta($vote_id, 'dlh_open_until', true);
		if (!$close) {
			return false;
		}

		$timezone = wp_timezone();
		$close_date = DateTime::createFromFormat('Y-m-d H:i:s', $close, $timezone);
		if (!$close_date) {
			return false;
		}

		$now = new DateTime('now', $timezone);
		return $now > $close_date;
	}


	private function vote_close_label($vote_id) {
		$close = get_post_meta($vote_id, 'dlh_open_until', true);
		if (!$close) {
			return __('No close date set.', 'draft-league-hub');
		}

		$formatted_close = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $close);
		if ($this->is_vote_closed($vote_id)) {
			return sprintf(__('Closed on %s', 'draft-league-hub'), $formatted_close);
		}

		return sprintf(__('Open until %s', 'draft-league-hub'), $formatted_close);
	}


	private function current_vote_key($create = false) {
		if (is_user_logged_in()) {
			return 'user_' . get_current_user_id();
		}

		$cookie_name = 'dlh_voter_id';
		$voter_id = sanitize_key(wp_unslash($_COOKIE[$cookie_name] ?? ''));

		if (!$voter_id && $create) {
			$voter_id = str_replace('-', '', wp_generate_uuid4());
			$cookie_options = array(
				'expires' => time() + YEAR_IN_SECONDS,
				'path' => COOKIEPATH ? COOKIEPATH : '/',
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			);
			if (COOKIE_DOMAIN) {
				$cookie_options['domain'] = COOKIE_DOMAIN;
			}

			setcookie(
				$cookie_name,
				$voter_id,
				$cookie_options
			);
			$_COOKIE[$cookie_name] = $voter_id;
		}

		return $voter_id ? 'anon_' . $voter_id : '';
	}


	private function get_current_vote_from_votes($votes, $vote_key) {
		if ($vote_key && isset($votes[$vote_key])) {
			return $votes[$vote_key];
		}

		if (is_user_logged_in()) {
			$legacy_key = get_current_user_id();
			return $votes[$legacy_key] ?? array();
		}

		return array();
	}
}
