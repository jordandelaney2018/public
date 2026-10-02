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

			$question = array(
				'key' => $key,
				'label' => $label,
				'type' => $type,
			);
			if ('choice' === $type) {
				$question['options'] = array_values(array_unique(array_filter(
					array_map('sanitize_text_field', array_slice($parts, 2)),
					static function ($option) {
						return '' !== $option;
					}
				)));
			}
			$questions[] = $question;
		}

		return $questions;
	}


	private function normalize_question_type($type) {
		$type = sanitize_key($type);
		return in_array($type, array('manager', 'text', 'choice'), true) ? $type : 'text';
	}


	private function validate_vote_questions($raw) {
		foreach ($this->parse_default_questions($raw) as $question) {
			if ('choice' === $question['type'] && empty($question['options'])) {
				return new WP_Error(
					'missing_vote_options',
					sprintf(__('Add at least one answer option for "%s" using Question|choice|Option one|Option two.', 'draft-league-hub'), $question['label'])
				);
			}
		}

		return true;
	}


	private function ensure_current_vote_month() {
		$timezone = wp_timezone();
		$now = new DateTime('now', $timezone);
		$month_key = $now->format('Y-m');
		$award_month = $this->vote_award_month_datetime($now);
		$award_key = $award_month->format('Y-m');
		$award_title = $award_month->format('F Y') . ' Awards';
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
			update_post_meta($post_id, 'dlh_award_month', $award_key);

			if (get_the_title($post_id) !== $award_title) {
				wp_update_post(
					array(
						'ID' => $post_id,
						'post_title' => $award_title,
					)
				);
			}

			$this->sync_open_vote_questions($post_id);

			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => 'dlh_vote_month',
				'post_status' => 'publish',
				'post_title' => $award_title,
			)
		);

		if (!is_wp_error($post_id) && $post_id) {
			$options = $this->get_options();
			update_post_meta($post_id, 'dlh_month', $month_key);
			update_post_meta($post_id, 'dlh_award_month', $award_key);
			update_post_meta($post_id, 'dlh_open_until', $close->format('Y-m-d H:i:s'));
			update_post_meta($post_id, 'dlh_questions', $this->parse_default_questions($options['default_questions']));
			update_post_meta($post_id, 'dlh_votes', array());
		}

		return absint($post_id);
	}


	private function sync_open_vote_questions($vote_id) {
		$vote_id = absint($vote_id);
		if (!$vote_id || $this->is_vote_closed($vote_id)) {
			return false;
		}

		$options = $this->get_options();
		$questions = $this->parse_default_questions($options['default_questions']);
		$current_questions = get_post_meta($vote_id, 'dlh_questions', true);
		$current_questions = is_array($current_questions) ? $current_questions : array();

		if ($current_questions === $questions) {
			return false;
		}

		return (bool) update_post_meta($vote_id, 'dlh_questions', $questions);
	}


	private function vote_award_month_datetime(DateTime $date) {
		$award_month = clone $date;
		$award_month->modify('first day of previous month')->setTime(0, 0, 0);

		return $award_month;
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


	private function vote_question_counts($question, $votes) {
		$key = sanitize_key($question['key'] ?? '');
		$type = $this->normalize_question_type($question['type'] ?? 'text');
		$counts = array();

		foreach ($votes as $vote) {
			$value = $vote['answers'][$key]['value'] ?? '';
			if (!is_scalar($value) || '' === $value || 0 === $value) {
				continue;
			}

			$label = 'manager' === $type ? $this->manager_name(absint($value)) : sanitize_text_field($value);
			if ('' !== $label) {
				$counts[$label] = ($counts[$label] ?? 0) + 1;
			}
		}

		arsort($counts);
		return $counts;
	}


	private function get_monthly_vote_history() {
		$ballots = get_posts(
			array(
				'post_type' => 'dlh_vote_month',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'orderby' => array('date' => 'DESC', 'ID' => 'DESC'),
				'meta_query' => array(
					array(
						'key' => 'dlh_open_until',
						'value' => current_time('mysql'),
						'compare' => '<',
						'type' => 'DATETIME',
					),
				),
			)
		);
		$history = array();

		foreach ($ballots as $ballot) {
			if (!$this->is_vote_closed($ballot->ID)) {
				continue;
			}

			$questions = get_post_meta($ballot->ID, 'dlh_questions', true);
			if (!is_array($questions) || !$questions) {
				continue;
			}
			$votes = get_post_meta($ballot->ID, 'dlh_votes', true);
			$votes = is_array($votes) ? $votes : array();
			$month = get_post_meta($ballot->ID, 'dlh_award_month', true);
			// Older ballots used their calendar month before award months were introduced.
			if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
				$month = get_post_meta($ballot->ID, 'dlh_month', true);
			}
			$month_date = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
				? DateTime::createFromFormat('!Y-m', $month, wp_timezone()) : false;
			$awards = array();

			foreach ($questions as $question) {
				$counts = $this->vote_question_counts($question, $votes);
				$winning_count = $counts ? max($counts) : 0;
				$awards[] = array(
					'label' => $question['label'] ?? '',
					'is_quote' => 'manager' !== $this->normalize_question_type($question['type'] ?? 'text') && false !== stripos($question['label'] ?? '', 'quote'),
					'winners' => array_keys($counts, $winning_count, true),
					'votes' => $winning_count,
				);
			}

			$history[] = array(
				'month' => $month_date ? $month_date->format('Y-m') : '',
				'label' => $month_date ? wp_date('F Y', $month_date->getTimestamp()) : get_the_title($ballot->ID),
				'awards' => $awards,
			);
		}

		usort($history, static function ($a, $b) {
			return strcmp($b['month'], $a['month']);
		});
		return $history;
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
