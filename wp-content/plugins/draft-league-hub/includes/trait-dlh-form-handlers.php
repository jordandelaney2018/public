<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Form_Handlers {


	public function maybe_handle_frontend_posts() {
		if (empty($_POST['dlh_action'])) {
			return;
		}

		$action = sanitize_key(wp_unslash($_POST['dlh_action']));

		if ('submit_vote' === $action) {
			$this->handle_vote_submission();
		}

		if ('add_sidebet' === $action) {
			$this->handle_sidebet_submission();
		}

		if ('create_poll' === $action) {
			$this->handle_poll_creation();
		}

		if ('respond_poll' === $action) {
			$this->handle_poll_response();
		}
	}


	public function handle_vote_submission() {
		$this->verify_nonce_or_die('dlh_submit_vote');

		if (!is_user_logged_in()) {
			$this->redirect_with_notice('login_required');
		}

		$vote_id = absint($_POST['vote_id'] ?? 0);
		if (!$vote_id || 'dlh_vote_month' !== get_post_type($vote_id)) {
			$this->redirect_with_notice('invalid_vote');
		}

		if ($this->is_vote_closed($vote_id)) {
			$this->redirect_with_notice('vote_closed');
		}

		$questions = get_post_meta($vote_id, 'dlh_questions', true);
		$questions = is_array($questions) ? $questions : array();
		$answers = array();

		foreach ($questions as $question) {
			$key = sanitize_key($question['key'] ?? '');
			$type = $this->normalize_question_type($question['type'] ?? 'text');
			if (!$key) {
				continue;
			}

			$value = $_POST['answer'][$key] ?? '';
			$reason = $_POST['reason'][$key] ?? '';

			if ('manager' === $type) {
				$value = absint($value);
			} else {
				$value = sanitize_text_field(wp_unslash($value));
			}

			$answers[$key] = array(
				'label' => sanitize_text_field($question['label'] ?? ''),
				'type' => $type,
				'value' => $value,
				'reason' => sanitize_textarea_field(wp_unslash($reason)),
			);
		}

		$user = wp_get_current_user();
		$votes = get_post_meta($vote_id, 'dlh_votes', true);
		$votes = is_array($votes) ? $votes : array();
		$votes[$user->ID] = array(
			'user_id' => $user->ID,
			'user_name' => $user->display_name,
			'submitted' => current_time('mysql'),
			'answers' => $answers,
		);

		update_post_meta($vote_id, 'dlh_votes', $votes);
		$this->redirect_with_notice('vote_saved');
	}


	public function handle_sidebet_submission() {
		$this->verify_nonce_or_die('dlh_add_sidebet');

		$options = $this->get_options();
		if (!empty($options['sidebets_require_login']) && !is_user_logged_in()) {
			$this->redirect_with_notice('login_required');
		}

		$manager_a = absint($_POST['manager_a'] ?? 0);
		$manager_b = absint($_POST['manager_b'] ?? 0);
		$stipulation = sanitize_textarea_field(wp_unslash($_POST['stipulation'] ?? ''));
		$stake = sanitize_text_field(wp_unslash($_POST['stake'] ?? ''));
		$due_date = sanitize_text_field(wp_unslash($_POST['due_date'] ?? ''));

		if (!$manager_a || !$manager_b || $manager_a === $manager_b || '' === $stipulation || '' === $stake) {
			$this->redirect_with_notice('missing_fields');
		}

		$title = sprintf(
			'%s v %s: %s',
			$this->manager_name($manager_a),
			$this->manager_name($manager_b),
			wp_trim_words($stipulation, 8, '')
		);

		$post_id = wp_insert_post(
			array(
				'post_type' => 'dlh_sidebet',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_content' => $stipulation,
				'post_author' => get_current_user_id(),
			)
		);

		if (is_wp_error($post_id) || !$post_id) {
			$this->redirect_with_notice('save_failed');
		}

		update_post_meta($post_id, 'dlh_manager_a', $manager_a);
		update_post_meta($post_id, 'dlh_manager_b', $manager_b);
		update_post_meta($post_id, 'dlh_stipulation', $stipulation);
		update_post_meta($post_id, 'dlh_stake', $stake);
		update_post_meta($post_id, 'dlh_due_date', $due_date);
		update_post_meta($post_id, 'dlh_status', 'active');
		update_post_meta($post_id, 'dlh_submitted_by', get_current_user_id());

		$this->redirect_with_notice('sidebet_saved');
	}


	public function handle_poll_creation() {
		$this->verify_nonce_or_die('dlh_create_poll');

		$options = $this->get_options();
		if (!empty($options['sidebets_require_login']) && !is_user_logged_in()) {
			$this->redirect_with_notice('login_required');
		}

		$title = sanitize_text_field(wp_unslash($_POST['poll_title'] ?? ''));
		$raw_options = sanitize_textarea_field(wp_unslash($_POST['poll_options'] ?? ''));
		$description = sanitize_textarea_field(wp_unslash($_POST['poll_description'] ?? ''));
		$poll_options = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw_options)));

		if ('' === $title || count($poll_options) < 2) {
			$this->redirect_with_notice('missing_fields');
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => 'dlh_event_poll',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_content' => $description,
				'post_author' => get_current_user_id(),
			)
		);

		if (is_wp_error($post_id) || !$post_id) {
			$this->redirect_with_notice('save_failed');
		}

		update_post_meta($post_id, 'dlh_options', array_values($poll_options));
		update_post_meta($post_id, 'dlh_responses', array());
		$this->redirect_with_notice('poll_saved');
	}


	public function handle_poll_response() {
		$this->verify_nonce_or_die('dlh_respond_poll');

		if (!is_user_logged_in()) {
			$this->redirect_with_notice('login_required');
		}

		$poll_id = absint($_POST['poll_id'] ?? 0);
		if (!$poll_id || 'dlh_event_poll' !== get_post_type($poll_id)) {
			$this->redirect_with_notice('invalid_poll');
		}

		$options = get_post_meta($poll_id, 'dlh_options', true);
		$options = is_array($options) ? $options : array();
		$answers = array();
		foreach ($options as $index => $label) {
			$value = sanitize_key(wp_unslash($_POST['availability'][$index] ?? ''));
			if (!in_array($value, array('yes', 'maybe', 'no'), true)) {
				$value = 'maybe';
			}
			$answers[$index] = $value;
		}

		$user = wp_get_current_user();
		$responses = get_post_meta($poll_id, 'dlh_responses', true);
		$responses = is_array($responses) ? $responses : array();
		$responses[$user->ID] = array(
			'user_id' => $user->ID,
			'user_name' => $user->display_name,
			'updated' => current_time('mysql'),
			'answers' => $answers,
		);

		update_post_meta($poll_id, 'dlh_responses', $responses);
		$this->redirect_with_notice('poll_response_saved');
	}
}
