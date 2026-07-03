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
			return absint($existing[0]);
		}

		$close = clone $now;
		$close->modify('last day of this month')->setTime(23, 59, 59);

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

		return sprintf(__('Open until %s', 'draft-league-hub'), mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $close));
	}
}
