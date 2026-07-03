<?php
/**
 * Plugin Name: Draft League Hub
 * Description: A small WordPress hub for FPL Draft leagues: joke news, monthly votes, sidebets, availability polls, and FPL Draft API widgets.
 * Version: 0.1.0
 * Author: Codex
 * Text Domain: draft-league-hub
 */

if (!defined('ABSPATH')) {
	exit;
}

final class DLH_Plugin {
	const VERSION = '0.1.0';
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
		add_action('init', array($this, 'maybe_handle_frontend_posts'), 20);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
		add_action('save_post_dlh_manager', array($this, 'save_manager_meta'));
		add_action('save_post_dlh_sidebet', array($this, 'save_sidebet_meta'));
		add_action(self::CRON_HOOK, array($this, 'daily_maintenance'));

		add_shortcode('dlh_home', array($this, 'shortcode_home'));
		add_shortcode('dlh_news', array($this, 'shortcode_news'));
		add_shortcode('dlh_monthly_votes', array($this, 'shortcode_monthly_votes'));
		add_shortcode('dlh_sidebets', array($this, 'shortcode_sidebets'));
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

	public function register_post_types() {
		register_post_type(
			'dlh_manager',
			array(
				'labels' => array(
					'name' => __('Managers', 'draft-league-hub'),
					'singular_name' => __('Manager', 'draft-league-hub'),
					'add_new_item' => __('Add Manager', 'draft-league-hub'),
					'edit_item' => __('Edit Manager', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'menu_icon' => 'dashicons-groups',
				'supports' => array('title', 'thumbnail'),
				'capability_type' => 'post',
			)
		);

		register_post_type(
			'dlh_news',
			array(
				'labels' => array(
					'name' => __('League News', 'draft-league-hub'),
					'singular_name' => __('News Story', 'draft-league-hub'),
					'add_new_item' => __('Add News Story', 'draft-league-hub'),
					'edit_item' => __('Edit News Story', 'draft-league-hub'),
				),
				'public' => true,
				'show_ui' => true,
				'show_in_rest' => true,
				'menu_icon' => 'dashicons-megaphone',
				'has_archive' => true,
				'rewrite' => array('slug' => 'league-news'),
				'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'author'),
			)
		);

		register_post_type(
			'dlh_sidebet',
			array(
				'labels' => array(
					'name' => __('Sidebets', 'draft-league-hub'),
					'singular_name' => __('Sidebet', 'draft-league-hub'),
					'add_new_item' => __('Add Sidebet', 'draft-league-hub'),
					'edit_item' => __('Edit Sidebet', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'menu_icon' => 'dashicons-money-alt',
				'supports' => array('title', 'editor', 'author'),
			)
		);

		register_post_type(
			'dlh_vote_month',
			array(
				'labels' => array(
					'name' => __('Monthly Votes', 'draft-league-hub'),
					'singular_name' => __('Monthly Vote', 'draft-league-hub'),
					'edit_item' => __('Edit Monthly Vote', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'menu_icon' => 'dashicons-awards',
				'supports' => array('title'),
			)
		);

		register_post_type(
			'dlh_event_poll',
			array(
				'labels' => array(
					'name' => __('Availability Polls', 'draft-league-hub'),
					'singular_name' => __('Availability Poll', 'draft-league-hub'),
					'edit_item' => __('Edit Availability Poll', 'draft-league-hub'),
				),
				'public' => false,
				'show_ui' => true,
				'menu_icon' => 'dashicons-calendar-alt',
				'supports' => array('title', 'editor', 'author'),
			)
		);
	}

	public function enqueue_assets() {
		wp_enqueue_style(
			'dlh-styles',
			plugins_url('assets/dlh.css', __FILE__),
			array(),
			self::VERSION
		);
	}

	public function admin_menu() {
		add_options_page(
			__('Draft League Hub', 'draft-league-hub'),
			__('Draft League Hub', 'draft-league-hub'),
			'manage_options',
			'draft-league-hub',
			array($this, 'render_settings_page')
		);
	}

	public function add_meta_boxes() {
		add_meta_box(
			'dlh_manager_details',
			__('Manager Details', 'draft-league-hub'),
			array($this, 'render_manager_meta_box'),
			'dlh_manager',
			'normal',
			'high'
		);

		add_meta_box(
			'dlh_sidebet_details',
			__('Sidebet Details', 'draft-league-hub'),
			array($this, 'render_sidebet_meta_box'),
			'dlh_sidebet',
			'normal',
			'high'
		);
	}

	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['dlh_save_settings'])) {
			check_admin_referer('dlh_save_settings');

			$options = $this->get_options();
			$options['league_name'] = sanitize_text_field(wp_unslash($_POST['league_name'] ?? ''));
			$options['season_label'] = sanitize_text_field(wp_unslash($_POST['season_label'] ?? ''));
			$options['hero_kicker'] = sanitize_text_field(wp_unslash($_POST['hero_kicker'] ?? ''));
			$options['hero_title'] = sanitize_text_field(wp_unslash($_POST['hero_title'] ?? ''));
			$options['hero_copy'] = sanitize_textarea_field(wp_unslash($_POST['hero_copy'] ?? ''));
			$options['fpl_league_id'] = preg_replace('/[^0-9]/', '', wp_unslash($_POST['fpl_league_id'] ?? ''));
			$options['cache_minutes'] = max(5, absint($_POST['cache_minutes'] ?? 30));
			$options['default_questions'] = sanitize_textarea_field(wp_unslash($_POST['default_questions'] ?? ''));
			$options['live_vote_results'] = !empty($_POST['live_vote_results']) ? 1 : 0;
			$options['sidebets_require_login'] = !empty($_POST['sidebets_require_login']) ? 1 : 0;

			update_option(self::OPTION, $options);
			echo '<div class="updated notice"><p>' . esc_html__('Draft League Hub settings saved.', 'draft-league-hub') . '</p></div>';
		}

		if (isset($_POST['dlh_create_pages'])) {
			check_admin_referer('dlh_create_pages');
			$this->create_default_pages();
			echo '<div class="updated notice"><p>' . esc_html__('Hub pages created or refreshed.', 'draft-league-hub') . '</p></div>';
		}

		$options = $this->get_options();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Draft League Hub', 'draft-league-hub'); ?></h1>
			<p><?php echo esc_html__('Set the league basics, then add managers and use the generated pages in your menu.', 'draft-league-hub'); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field('dlh_save_settings'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="league_name"><?php echo esc_html__('League name', 'draft-league-hub'); ?></label></th>
						<td><input name="league_name" id="league_name" type="text" class="regular-text" value="<?php echo esc_attr($options['league_name']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="season_label"><?php echo esc_html__('Season label', 'draft-league-hub'); ?></label></th>
						<td><input name="season_label" id="season_label" type="text" class="regular-text" value="<?php echo esc_attr($options['season_label']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hero_kicker"><?php echo esc_html__('Hero eyebrow', 'draft-league-hub'); ?></label></th>
						<td><input name="hero_kicker" id="hero_kicker" type="text" class="regular-text" value="<?php echo esc_attr($options['hero_kicker']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hero_title"><?php echo esc_html__('Hero title', 'draft-league-hub'); ?></label></th>
						<td><input name="hero_title" id="hero_title" type="text" class="regular-text" value="<?php echo esc_attr($options['hero_title']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hero_copy"><?php echo esc_html__('Hero copy', 'draft-league-hub'); ?></label></th>
						<td><textarea name="hero_copy" id="hero_copy" class="large-text" rows="3"><?php echo esc_textarea($options['hero_copy']); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="fpl_league_id"><?php echo esc_html__('FPL Draft league ID', 'draft-league-hub'); ?></label></th>
						<td>
							<input name="fpl_league_id" id="fpl_league_id" type="text" class="regular-text" value="<?php echo esc_attr($options['fpl_league_id']); ?>">
							<p class="description"><?php echo esc_html__('Used for cached calls to draft.premierleague.com. Leave blank until you want live stats.', 'draft-league-hub'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cache_minutes"><?php echo esc_html__('API cache minutes', 'draft-league-hub'); ?></label></th>
						<td><input name="cache_minutes" id="cache_minutes" type="number" min="5" step="5" value="<?php echo esc_attr($options['cache_minutes']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="default_questions"><?php echo esc_html__('Monthly vote questions', 'draft-league-hub'); ?></label></th>
						<td>
							<textarea name="default_questions" id="default_questions" class="large-text code" rows="8"><?php echo esc_textarea($options['default_questions']); ?></textarea>
							<p class="description"><?php echo esc_html__('One per line. Format: Question|manager or Question|text. New months copy these defaults.', 'draft-league-hub'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Front-end behaviour', 'draft-league-hub'); ?></th>
						<td>
							<label><input type="checkbox" name="live_vote_results" value="1" <?php checked($options['live_vote_results'], 1); ?>> <?php echo esc_html__('Show vote results before the month closes', 'draft-league-hub'); ?></label><br>
							<label><input type="checkbox" name="sidebets_require_login" value="1" <?php checked($options['sidebets_require_login'], 1); ?>> <?php echo esc_html__('Require login to add sidebets and availability polls', 'draft-league-hub'); ?></label>
						</td>
					</tr>
				</table>
				<p><button type="submit" name="dlh_save_settings" class="button button-primary"><?php echo esc_html__('Save settings', 'draft-league-hub'); ?></button></p>
			</form>

			<hr>
			<h2><?php echo esc_html__('Pages and Shortcodes', 'draft-league-hub'); ?></h2>
			<p><?php echo esc_html__('The plugin can create starter pages using the shortcodes below. Add them to your navigation menu after creation.', 'draft-league-hub'); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field('dlh_create_pages'); ?>
				<p><button type="submit" name="dlh_create_pages" class="button"><?php echo esc_html__('Create starter pages', 'draft-league-hub'); ?></button></p>
			</form>
			<ul>
				<li><code>[dlh_home]</code> <?php echo esc_html__('Front-page hero and latest joke news', 'draft-league-hub'); ?></li>
				<li><code>[dlh_news]</code> <?php echo esc_html__('News listing', 'draft-league-hub'); ?></li>
				<li><code>[dlh_monthly_votes]</code> <?php echo esc_html__('Auto-generated monthly ballot', 'draft-league-hub'); ?></li>
				<li><code>[dlh_sidebets]</code> <?php echo esc_html__('Sidebet board and submission form', 'draft-league-hub'); ?></li>
				<li><code>[dlh_calendar]</code> <?php echo esc_html__('Availability poll board', 'draft-league-hub'); ?></li>
				<li><code>[dlh_stats]</code> <?php echo esc_html__('Cached FPL Draft league standings widget', 'draft-league-hub'); ?></li>
			</ul>
		</div>
		<?php
	}

	public function render_manager_meta_box($post) {
		wp_nonce_field('dlh_save_manager_meta', 'dlh_manager_nonce');

		$fields = array(
			'dlh_real_name' => __('Real name', 'draft-league-hub'),
			'dlh_team_name' => __('FPL team name', 'draft-league-hub'),
			'dlh_supported_club' => __('Real-life club supported', 'draft-league-hub'),
			'dlh_fpl_entry_id' => __('FPL Draft entry ID', 'draft-league-hub'),
		);

		echo '<table class="form-table" role="presentation">';
		foreach ($fields as $key => $label) {
			$value = get_post_meta($post->ID, $key, true);
			echo '<tr><th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
			echo '<input type="text" class="regular-text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
			echo '</td></tr>';
		}
		echo '</table>';
	}

	public function render_sidebet_meta_box($post) {
		wp_nonce_field('dlh_save_sidebet_meta', 'dlh_sidebet_nonce');

		$manager_a = absint(get_post_meta($post->ID, 'dlh_manager_a', true));
		$manager_b = absint(get_post_meta($post->ID, 'dlh_manager_b', true));
		$winner = absint(get_post_meta($post->ID, 'dlh_winner', true));
		$stipulation = get_post_meta($post->ID, 'dlh_stipulation', true);
		$stake = get_post_meta($post->ID, 'dlh_stake', true);
		$due_date = get_post_meta($post->ID, 'dlh_due_date', true);
		$status = get_post_meta($post->ID, 'dlh_status', true);
		$status = $status ? $status : 'active';

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__('Manager A', 'draft-league-hub') . '</th><td>' . $this->manager_select('dlh_manager_a', $manager_a) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Manager B', 'draft-league-hub') . '</th><td>' . $this->manager_select('dlh_manager_b', $manager_b) . '</td></tr>';
		echo '<tr><th scope="row"><label for="dlh_stipulation">' . esc_html__('Stipulation', 'draft-league-hub') . '</label></th><td><textarea id="dlh_stipulation" name="dlh_stipulation" class="large-text" rows="3">' . esc_textarea($stipulation) . '</textarea></td></tr>';
		echo '<tr><th scope="row"><label for="dlh_stake">' . esc_html__('Stake', 'draft-league-hub') . '</label></th><td><input type="text" class="regular-text" id="dlh_stake" name="dlh_stake" value="' . esc_attr($stake) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="dlh_due_date">' . esc_html__('Due date', 'draft-league-hub') . '</label></th><td><input type="date" id="dlh_due_date" name="dlh_due_date" value="' . esc_attr($due_date) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="dlh_status">' . esc_html__('Status', 'draft-league-hub') . '</label></th><td><select id="dlh_status" name="dlh_status">';
		foreach ($this->sidebet_statuses() as $key => $label) {
			echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Winner', 'draft-league-hub') . '</th><td>' . $this->manager_select('dlh_winner', $winner, __('No winner yet', 'draft-league-hub')) . '</td></tr>';
		echo '</table>';
	}

	public function save_manager_meta($post_id) {
		if (!$this->can_save_post($post_id, 'dlh_manager_nonce', 'dlh_save_manager_meta')) {
			return;
		}

		$fields = array('dlh_real_name', 'dlh_team_name', 'dlh_supported_club', 'dlh_fpl_entry_id');
		foreach ($fields as $field) {
			$value = sanitize_text_field(wp_unslash($_POST[$field] ?? ''));
			update_post_meta($post_id, $field, $value);
		}
	}

	public function save_sidebet_meta($post_id) {
		if (!$this->can_save_post($post_id, 'dlh_sidebet_nonce', 'dlh_save_sidebet_meta')) {
			return;
		}

		update_post_meta($post_id, 'dlh_manager_a', absint($_POST['dlh_manager_a'] ?? 0));
		update_post_meta($post_id, 'dlh_manager_b', absint($_POST['dlh_manager_b'] ?? 0));
		update_post_meta($post_id, 'dlh_winner', absint($_POST['dlh_winner'] ?? 0));
		update_post_meta($post_id, 'dlh_stipulation', sanitize_textarea_field(wp_unslash($_POST['dlh_stipulation'] ?? '')));
		update_post_meta($post_id, 'dlh_stake', sanitize_text_field(wp_unslash($_POST['dlh_stake'] ?? '')));
		update_post_meta($post_id, 'dlh_due_date', sanitize_text_field(wp_unslash($_POST['dlh_due_date'] ?? '')));

		$status = sanitize_key(wp_unslash($_POST['dlh_status'] ?? 'active'));
		if (!array_key_exists($status, $this->sidebet_statuses())) {
			$status = 'active';
		}
		update_post_meta($post_id, 'dlh_status', $status);
	}

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

	public function shortcode_home() {
		$options = $this->get_options();
		$pages = $this->get_page_links();
		ob_start();
		?>
		<div class="dlh-wrap">
			<section class="dlh-hero">
				<div class="dlh-hero__content">
					<p class="dlh-kicker"><?php echo esc_html($options['hero_kicker']); ?></p>
					<h1><?php echo esc_html($options['hero_title']); ?></h1>
					<p><?php echo esc_html($options['hero_copy']); ?></p>
					<div class="dlh-actions">
						<?php foreach ($pages as $key => $page) : ?>
							<?php if (in_array($key, array('votes', 'sidebets', 'calendar', 'stats'), true)) : ?>
								<a class="dlh-button" href="<?php echo esc_url($page['url']); ?>"><?php echo esc_html($page['label']); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="dlh-hero__panel">
					<span><?php echo esc_html($options['season_label']); ?></span>
					<strong><?php echo esc_html($options['league_name']); ?></strong>
					<small><?php echo esc_html__('Questionable trades. Monthly slander. Receipts kept forever.', 'draft-league-hub'); ?></small>
				</div>
			</section>
			<section class="dlh-section">
				<div class="dlh-section__head">
					<h2><?php echo esc_html__('Latest League News', 'draft-league-hub'); ?></h2>
					<?php if (!empty($pages['news'])) : ?>
						<a href="<?php echo esc_url($pages['news']['url']); ?>"><?php echo esc_html__('All news', 'draft-league-hub'); ?></a>
					<?php endif; ?>
				</div>
				<?php echo $this->render_news_cards(3); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	public function shortcode_news($atts = array()) {
		$atts = shortcode_atts(array('count' => 12), $atts, 'dlh_news');
		ob_start();
		echo '<div class="dlh-wrap dlh-section">';
		echo '<div class="dlh-section__head"><h2>' . esc_html__('League News', 'draft-league-hub') . '</h2></div>';
		echo $this->render_news_cards(absint($atts['count'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		return ob_get_clean();
	}

	public function shortcode_monthly_votes() {
		$vote_id = $this->ensure_current_vote_month();
		$options = $this->get_options();
		$questions = get_post_meta($vote_id, 'dlh_questions', true);
		$questions = is_array($questions) ? $questions : array();
		$votes = get_post_meta($vote_id, 'dlh_votes', true);
		$votes = is_array($votes) ? $votes : array();
		$user_vote = array();

		if (is_user_logged_in()) {
			$user_vote = $votes[get_current_user_id()] ?? array();
		}

		$show_results = !empty($options['live_vote_results']) || $this->is_vote_closed($vote_id) || current_user_can('edit_posts');

		ob_start();
		?>
		<div class="dlh-wrap dlh-section">
			<?php $this->render_notice(); ?>
			<div class="dlh-section__head">
				<div>
					<h2><?php echo esc_html(get_the_title($vote_id)); ?></h2>
					<p><?php echo esc_html($this->vote_close_label($vote_id)); ?></p>
				</div>
				<span class="dlh-pill"><?php echo esc_html(sprintf(_n('%d vote', '%d votes', count($votes), 'draft-league-hub'), count($votes))); ?></span>
			</div>

			<?php if (!is_user_logged_in()) : ?>
				<div class="dlh-empty"><?php echo esc_html__('Log in to submit this month\'s vote.', 'draft-league-hub'); ?></div>
			<?php elseif ($this->is_vote_closed($vote_id)) : ?>
				<div class="dlh-empty"><?php echo esc_html__('Voting is closed for this month.', 'draft-league-hub'); ?></div>
			<?php else : ?>
				<form class="dlh-form" method="post" action="">
					<input type="hidden" name="dlh_action" value="submit_vote">
					<input type="hidden" name="vote_id" value="<?php echo esc_attr($vote_id); ?>">
					<?php wp_nonce_field('dlh_submit_vote', 'dlh_nonce'); ?>
					<?php foreach ($questions as $question) : ?>
						<?php
						$key = sanitize_key($question['key'] ?? '');
						$type = $this->normalize_question_type($question['type'] ?? 'text');
						$current_answer = $user_vote['answers'][$key]['value'] ?? '';
						$current_reason = $user_vote['answers'][$key]['reason'] ?? '';
						?>
						<div class="dlh-fieldset">
							<label for="answer-<?php echo esc_attr($key); ?>"><?php echo esc_html($question['label']); ?></label>
							<?php if ('manager' === $type) : ?>
								<?php echo $this->manager_select('answer[' . esc_attr($key) . ']', absint($current_answer), __('Choose manager', 'draft-league-hub'), 'answer-' . esc_attr($key)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php else : ?>
								<input id="answer-<?php echo esc_attr($key); ?>" type="text" name="answer[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($current_answer); ?>" placeholder="<?php echo esc_attr__('Nomination', 'draft-league-hub'); ?>">
							<?php endif; ?>
							<textarea name="reason[<?php echo esc_attr($key); ?>]" rows="2" placeholder="<?php echo esc_attr__('Optional reason / evidence', 'draft-league-hub'); ?>"><?php echo esc_textarea($current_reason); ?></textarea>
						</div>
					<?php endforeach; ?>
					<button class="dlh-button" type="submit"><?php echo esc_html($user_vote ? __('Update vote', 'draft-league-hub') : __('Submit vote', 'draft-league-hub')); ?></button>
				</form>
			<?php endif; ?>

			<?php if ($show_results) : ?>
				<div class="dlh-results">
					<h3><?php echo esc_html__('Results', 'draft-league-hub'); ?></h3>
					<?php echo $this->render_vote_results($questions, $votes); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function shortcode_sidebets() {
		$options = $this->get_options();
		$require_login = !empty($options['sidebets_require_login']);
		$query = new WP_Query(
			array(
				'post_type' => 'dlh_sidebet',
				'post_status' => 'publish',
				'posts_per_page' => 100,
				'orderby' => 'date',
				'order' => 'DESC',
			)
		);

		ob_start();
		?>
		<div class="dlh-wrap dlh-section">
			<?php $this->render_notice(); ?>
			<div class="dlh-section__head">
				<h2><?php echo esc_html__('Sidebets', 'draft-league-hub'); ?></h2>
				<span class="dlh-pill"><?php echo esc_html(sprintf(_n('%d bet', '%d bets', $query->found_posts, 'draft-league-hub'), $query->found_posts)); ?></span>
			</div>

			<?php if ($require_login && !is_user_logged_in()) : ?>
				<div class="dlh-empty"><?php echo esc_html__('Log in to add a sidebet.', 'draft-league-hub'); ?></div>
			<?php else : ?>
				<form class="dlh-form dlh-form--compact" method="post" action="">
					<h3><?php echo esc_html__('Add Sidebet', 'draft-league-hub'); ?></h3>
					<input type="hidden" name="dlh_action" value="add_sidebet">
					<?php wp_nonce_field('dlh_add_sidebet', 'dlh_nonce'); ?>
					<div class="dlh-grid dlh-grid--two">
						<div class="dlh-fieldset">
							<label><?php echo esc_html__('Manager A', 'draft-league-hub'); ?></label>
							<?php echo $this->manager_select('manager_a'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="dlh-fieldset">
							<label><?php echo esc_html__('Manager B', 'draft-league-hub'); ?></label>
							<?php echo $this->manager_select('manager_b'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
					<div class="dlh-fieldset">
						<label for="stipulation"><?php echo esc_html__('Stipulation', 'draft-league-hub'); ?></label>
						<textarea id="stipulation" name="stipulation" rows="3" required placeholder="<?php echo esc_attr__('Example: Sam to finish above Liam, loser buys first round on draft night.', 'draft-league-hub'); ?>"></textarea>
					</div>
					<div class="dlh-grid dlh-grid--two">
						<div class="dlh-fieldset">
							<label for="stake"><?php echo esc_html__('Stake', 'draft-league-hub'); ?></label>
							<input id="stake" name="stake" type="text" required placeholder="<?php echo esc_attr__('Pint, tenner, profile picture, etc.', 'draft-league-hub'); ?>">
						</div>
						<div class="dlh-fieldset">
							<label for="due_date"><?php echo esc_html__('Due date', 'draft-league-hub'); ?></label>
							<input id="due_date" name="due_date" type="date">
						</div>
					</div>
					<button class="dlh-button" type="submit"><?php echo esc_html__('Add sidebet', 'draft-league-hub'); ?></button>
				</form>
			<?php endif; ?>

			<div class="dlh-card-grid">
				<?php if ($query->have_posts()) : ?>
					<?php while ($query->have_posts()) : ?>
						<?php $query->the_post(); ?>
						<?php echo $this->render_sidebet_card(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<div class="dlh-empty"><?php echo esc_html__('No sidebets yet. Suspiciously sensible behaviour.', 'draft-league-hub'); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function shortcode_calendar() {
		$options = $this->get_options();
		$require_login = !empty($options['sidebets_require_login']);
		$query = new WP_Query(
			array(
				'post_type' => 'dlh_event_poll',
				'post_status' => 'publish',
				'posts_per_page' => 20,
				'orderby' => 'date',
				'order' => 'DESC',
			)
		);

		ob_start();
		?>
		<div class="dlh-wrap dlh-section">
			<?php $this->render_notice(); ?>
			<div class="dlh-section__head">
				<h2><?php echo esc_html__('Calendar & Availability', 'draft-league-hub'); ?></h2>
			</div>

			<?php if ($require_login && !is_user_logged_in()) : ?>
				<div class="dlh-empty"><?php echo esc_html__('Log in to create an availability poll.', 'draft-league-hub'); ?></div>
			<?php else : ?>
				<form class="dlh-form dlh-form--compact" method="post" action="">
					<h3><?php echo esc_html__('Create Availability Poll', 'draft-league-hub'); ?></h3>
					<input type="hidden" name="dlh_action" value="create_poll">
					<?php wp_nonce_field('dlh_create_poll', 'dlh_nonce'); ?>
					<div class="dlh-fieldset">
						<label for="poll_title"><?php echo esc_html__('Title', 'draft-league-hub'); ?></label>
						<input id="poll_title" name="poll_title" type="text" required placeholder="<?php echo esc_attr__('Draft night, trade deadline call, end of season drinks...', 'draft-league-hub'); ?>">
					</div>
					<div class="dlh-fieldset">
						<label for="poll_options"><?php echo esc_html__('Date/time options', 'draft-league-hub'); ?></label>
						<textarea id="poll_options" name="poll_options" rows="4" required placeholder="<?php echo esc_attr__("Fri 9 Aug, 7:30pm\nSat 10 Aug, 2:00pm\nSun 11 Aug, 8:00pm", 'draft-league-hub'); ?>"></textarea>
					</div>
					<div class="dlh-fieldset">
						<label for="poll_description"><?php echo esc_html__('Notes', 'draft-league-hub'); ?></label>
						<textarea id="poll_description" name="poll_description" rows="2"></textarea>
					</div>
					<button class="dlh-button" type="submit"><?php echo esc_html__('Create poll', 'draft-league-hub'); ?></button>
				</form>
			<?php endif; ?>

			<div class="dlh-stack">
				<?php if ($query->have_posts()) : ?>
					<?php while ($query->have_posts()) : ?>
						<?php $query->the_post(); ?>
						<?php echo $this->render_poll(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<div class="dlh-empty"><?php echo esc_html__('No availability polls yet.', 'draft-league-hub'); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function shortcode_stats() {
		$options = $this->get_options();
		$league_id = $options['fpl_league_id'];
		ob_start();
		?>
		<div class="dlh-wrap dlh-section">
			<div class="dlh-section__head">
				<h2><?php echo esc_html__('League Stats', 'draft-league-hub'); ?></h2>
				<span class="dlh-pill"><?php echo esc_html__('FPL Draft API', 'draft-league-hub'); ?></span>
			</div>
			<?php if (!$league_id) : ?>
				<div class="dlh-empty"><?php echo esc_html__('Add your FPL Draft league ID in Settings > Draft League Hub to enable live standings.', 'draft-league-hub'); ?></div>
			<?php else : ?>
				<?php
				$details = $this->api_get('/api/league/' . rawurlencode($league_id) . '/details');
				if (is_wp_error($details)) {
					echo '<div class="dlh-empty">' . esc_html($details->get_error_message()) . '</div>';
				} else {
					echo $this->render_standings($details); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function daily_maintenance() {
		$this->ensure_current_vote_month();
	}

	private function defaults() {
		return array(
			'league_name' => 'Draft League HQ',
			'season_label' => '2026/27',
			'hero_kicker' => 'FPL Draft',
			'hero_title' => 'Draft League HQ',
			'hero_copy' => 'Questionable trades, monthly slander, and sidebets that definitely started as jokes.',
			'fpl_league_id' => '',
			'cache_minutes' => 30,
			'live_vote_results' => 1,
			'sidebets_require_login' => 1,
			'default_questions' => implode(
				"\n",
				array(
					'Manager of the month|manager',
					'Worst manager of the month|manager',
					'Best trade of the month|text',
					'Worst trade of the month|text',
					'Most fraudulent points haul|manager',
					'Waiver wire disasterclass|text',
					'Quote or meltdown of the month|text',
				)
			),
			'page_ids' => array(),
		);
	}

	private function get_options() {
		$options = get_option(self::OPTION, array());
		if (!is_array($options)) {
			$options = array();
		}

		return array_merge($this->defaults(), $options);
	}

	private function schedule_cron() {
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
		}
	}

	private function create_default_pages() {
		$options = $this->get_options();
		$pages = array(
			'home' => array('title' => 'League Home', 'slug' => 'league-home', 'shortcode' => '[dlh_home]'),
			'news' => array('title' => 'League News', 'slug' => 'league-newsroom', 'shortcode' => '[dlh_news]'),
			'votes' => array('title' => 'Monthly Votes', 'slug' => 'monthly-votes', 'shortcode' => '[dlh_monthly_votes]'),
			'sidebets' => array('title' => 'Sidebets', 'slug' => 'sidebets', 'shortcode' => '[dlh_sidebets]'),
			'calendar' => array('title' => 'Calendar', 'slug' => 'calendar', 'shortcode' => '[dlh_calendar]'),
			'stats' => array('title' => 'League Stats', 'slug' => 'league-stats', 'shortcode' => '[dlh_stats]'),
		);

		foreach ($pages as $key => $page) {
			$existing = get_page_by_path($page['slug']);
			if ($existing) {
				$options['page_ids'][$key] = $existing->ID;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_type' => 'page',
					'post_status' => 'publish',
					'post_title' => $page['title'],
					'post_name' => $page['slug'],
					'post_content' => $page['shortcode'],
				)
			);

			if (!is_wp_error($page_id) && $page_id) {
				update_post_meta($page_id, '_dlh_created_page', 1);
				$options['page_ids'][$key] = $page_id;
			}
		}

		update_option(self::OPTION, $options);
	}

	private function get_page_links() {
		$options = $this->get_options();
		$labels = array(
			'home' => __('Home', 'draft-league-hub'),
			'news' => __('News', 'draft-league-hub'),
			'votes' => __('Monthly Votes', 'draft-league-hub'),
			'sidebets' => __('Sidebets', 'draft-league-hub'),
			'calendar' => __('Calendar', 'draft-league-hub'),
			'stats' => __('Stats', 'draft-league-hub'),
		);
		$links = array();

		foreach ($labels as $key => $label) {
			$page_id = absint($options['page_ids'][$key] ?? 0);
			if ($page_id && 'publish' === get_post_status($page_id)) {
				$links[$key] = array(
					'label' => $label,
					'url' => get_permalink($page_id),
				);
			}
		}

		return $links;
	}

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

	private function render_news_cards($count) {
		$query = new WP_Query(
			array(
				'post_type' => 'dlh_news',
				'post_status' => 'publish',
				'posts_per_page' => max(1, $count),
			)
		);

		ob_start();
		echo '<div class="dlh-card-grid">';
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				echo '<article class="dlh-card">';
				if (has_post_thumbnail()) {
					echo '<a class="dlh-card__image" href="' . esc_url(get_permalink()) . '">' . get_the_post_thumbnail(get_the_ID(), 'medium_large') . '</a>';
				}
				echo '<div class="dlh-card__body">';
				echo '<p class="dlh-kicker">' . esc_html(get_the_date()) . '</p>';
				echo '<h3><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
				echo '<p>' . esc_html(wp_trim_words(get_the_excerpt() ? get_the_excerpt() : wp_strip_all_tags(get_the_content()), 24)) . '</p>';
				echo '</div></article>';
			}
			wp_reset_postdata();
		} else {
			echo '<div class="dlh-empty">' . esc_html__('No league news yet. Add a story under League News in the dashboard.', 'draft-league-hub') . '</div>';
		}
		echo '</div>';

		return ob_get_clean();
	}

	private function render_vote_results($questions, $votes) {
		if (empty($votes)) {
			return '<div class="dlh-empty">' . esc_html__('No votes submitted yet.', 'draft-league-hub') . '</div>';
		}

		ob_start();
		echo '<div class="dlh-result-grid">';
		foreach ($questions as $question) {
			$key = sanitize_key($question['key'] ?? '');
			$type = $this->normalize_question_type($question['type'] ?? 'text');
			$counts = array();

			foreach ($votes as $vote) {
				$value = $vote['answers'][$key]['value'] ?? '';
				if ('' === $value || 0 === $value) {
					continue;
				}

				$label = 'manager' === $type ? $this->manager_name(absint($value)) : sanitize_text_field($value);
				if (!$label) {
					continue;
				}

				if (!isset($counts[$label])) {
					$counts[$label] = 0;
				}
				$counts[$label]++;
			}

			arsort($counts);
			echo '<div class="dlh-result-card">';
			echo '<h4>' . esc_html($question['label'] ?? '') . '</h4>';
			if (empty($counts)) {
				echo '<p>' . esc_html__('No nominations yet.', 'draft-league-hub') . '</p>';
			} else {
				echo '<ol>';
				foreach ($counts as $label => $count) {
					echo '<li><span>' . esc_html($label) . '</span><strong>' . esc_html($count) . '</strong></li>';
				}
				echo '</ol>';
			}
			echo '</div>';
		}
		echo '</div>';

		return ob_get_clean();
	}

	private function render_sidebet_card($post_id) {
		$manager_a = absint(get_post_meta($post_id, 'dlh_manager_a', true));
		$manager_b = absint(get_post_meta($post_id, 'dlh_manager_b', true));
		$winner = absint(get_post_meta($post_id, 'dlh_winner', true));
		$status = get_post_meta($post_id, 'dlh_status', true);
		$stake = get_post_meta($post_id, 'dlh_stake', true);
		$due_date = get_post_meta($post_id, 'dlh_due_date', true);
		$stipulation = get_post_meta($post_id, 'dlh_stipulation', true);
		$statuses = $this->sidebet_statuses();

		ob_start();
		echo '<article class="dlh-card dlh-sidebet">';
		echo '<div class="dlh-card__body">';
		echo '<div class="dlh-card__meta"><span class="dlh-pill">' . esc_html($statuses[$status] ?? __('Active', 'draft-league-hub')) . '</span>';
		if ($due_date) {
			echo '<span>' . esc_html(mysql2date(get_option('date_format'), $due_date)) . '</span>';
		}
		echo '</div>';
		echo '<h3>' . esc_html(get_the_title($post_id)) . '</h3>';
		echo '<p>' . esc_html($stipulation) . '</p>';
		echo '<dl class="dlh-mini-list">';
		echo '<div><dt>' . esc_html__('Managers', 'draft-league-hub') . '</dt><dd>' . esc_html($this->manager_name($manager_a)) . ' vs ' . esc_html($this->manager_name($manager_b)) . '</dd></div>';
		echo '<div><dt>' . esc_html__('Stake', 'draft-league-hub') . '</dt><dd>' . esc_html($stake) . '</dd></div>';
		if ($winner) {
			echo '<div><dt>' . esc_html__('Winner', 'draft-league-hub') . '</dt><dd>' . esc_html($this->manager_name($winner)) . '</dd></div>';
		}
		echo '</dl>';
		echo '</div></article>';

		return ob_get_clean();
	}

	private function render_poll($post_id) {
		$options = get_post_meta($post_id, 'dlh_options', true);
		$options = is_array($options) ? $options : array();
		$responses = get_post_meta($post_id, 'dlh_responses', true);
		$responses = is_array($responses) ? $responses : array();
		$user_response = is_user_logged_in() ? ($responses[get_current_user_id()] ?? array()) : array();

		ob_start();
		echo '<article class="dlh-panel">';
		echo '<div class="dlh-section__head"><div><h3>' . esc_html(get_the_title($post_id)) . '</h3>';
		if (get_post_field('post_content', $post_id)) {
			echo '<p>' . esc_html(wp_strip_all_tags(get_post_field('post_content', $post_id))) . '</p>';
		}
		echo '</div><span class="dlh-pill">' . esc_html(sprintf(_n('%d response', '%d responses', count($responses), 'draft-league-hub'), count($responses))) . '</span></div>';

		if (is_user_logged_in()) {
			echo '<form method="post" action="" class="dlh-availability">';
			echo '<input type="hidden" name="dlh_action" value="respond_poll">';
			echo '<input type="hidden" name="poll_id" value="' . esc_attr($post_id) . '">';
			wp_nonce_field('dlh_respond_poll', 'dlh_nonce');
			foreach ($options as $index => $label) {
				$current = $user_response['answers'][$index] ?? 'maybe';
				echo '<div class="dlh-availability__row">';
				echo '<strong>' . esc_html($label) . '</strong>';
				echo '<div class="dlh-segmented">';
				foreach (array('yes' => __('Yes', 'draft-league-hub'), 'maybe' => __('Maybe', 'draft-league-hub'), 'no' => __('No', 'draft-league-hub')) as $value => $text) {
					echo '<label><input type="radio" name="availability[' . esc_attr($index) . ']" value="' . esc_attr($value) . '" ' . checked($current, $value, false) . '><span>' . esc_html($text) . '</span></label>';
				}
				echo '</div></div>';
			}
			echo '<button class="dlh-button" type="submit">' . esc_html__('Save availability', 'draft-league-hub') . '</button>';
			echo '</form>';
		} else {
			echo '<div class="dlh-empty">' . esc_html__('Log in to add your availability.', 'draft-league-hub') . '</div>';
		}

		if (!empty($responses)) {
			echo '<div class="dlh-table-wrap"><table class="dlh-table"><thead><tr><th>' . esc_html__('Manager', 'draft-league-hub') . '</th>';
			foreach ($options as $label) {
				echo '<th>' . esc_html($label) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ($responses as $response) {
				echo '<tr><td>' . esc_html($response['user_name'] ?? '') . '</td>';
				foreach ($options as $index => $label) {
					$value = $response['answers'][$index] ?? 'maybe';
					echo '<td><span class="dlh-availability-pill dlh-availability-pill--' . esc_attr($value) . '">' . esc_html(ucfirst($value)) . '</span></td>';
				}
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}

		echo '</article>';
		return ob_get_clean();
	}

	private function render_standings($details) {
		$league = $details['league']['name'] ?? '';
		$entries = $details['league_entries'] ?? array();
		$entry_map = array();
		foreach ($entries as $entry) {
			$id = $entry['id'] ?? ($entry['entry_id'] ?? 0);
			if ($id) {
				$entry_map[$id] = $entry;
			}
		}

		$standings = $details['standings']['results'] ?? ($details['standings'] ?? array());
		if (!is_array($standings)) {
			$standings = array();
		}

		ob_start();
		if ($league) {
			echo '<h3>' . esc_html($league) . '</h3>';
		}

		if (empty($standings)) {
			echo '<div class="dlh-empty">' . esc_html__('The API responded, but no standings were available yet.', 'draft-league-hub') . '</div>';
			return ob_get_clean();
		}

		echo '<div class="dlh-table-wrap"><table class="dlh-table">';
		echo '<thead><tr><th>' . esc_html__('Rank', 'draft-league-hub') . '</th><th>' . esc_html__('Team', 'draft-league-hub') . '</th><th>' . esc_html__('Manager', 'draft-league-hub') . '</th><th>' . esc_html__('Points', 'draft-league-hub') . '</th><th>' . esc_html__('Played', 'draft-league-hub') . '</th><th>' . esc_html__('Record', 'draft-league-hub') . '</th></tr></thead><tbody>';

		foreach ($standings as $index => $row) {
			if (!is_array($row)) {
				continue;
			}

			$entry_id = $row['entry'] ?? ($row['entry_id'] ?? ($row['id'] ?? 0));
			$entry = $entry_id && isset($entry_map[$entry_id]) ? $entry_map[$entry_id] : array();
			$rank = $row['rank'] ?? ($row['position'] ?? ($index + 1));
			$team = $row['entry_name'] ?? ($entry['entry_name'] ?? ($entry['name'] ?? __('Unknown team', 'draft-league-hub')));
			$manager = $row['player_name'] ?? trim(($entry['player_first_name'] ?? '') . ' ' . ($entry['player_last_name'] ?? ''));
			$points = $row['total'] ?? ($row['points'] ?? ($row['matches_won'] ?? ''));
			$played = $row['matches_played'] ?? ($row['played'] ?? '');
			$record_parts = array();
			foreach (array('matches_won' => 'W', 'matches_drawn' => 'D', 'matches_lost' => 'L') as $key => $label) {
				if (isset($row[$key])) {
					$record_parts[] = $label . ':' . $row[$key];
				}
			}

			echo '<tr>';
			echo '<td>' . esc_html($rank) . '</td>';
			echo '<td>' . esc_html($team) . '</td>';
			echo '<td>' . esc_html($manager ? $manager : '-') . '</td>';
			echo '<td>' . esc_html($points) . '</td>';
			echo '<td>' . esc_html($played) . '</td>';
			echo '<td>' . esc_html(implode(' ', $record_parts)) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '<p class="dlh-footnote">' . esc_html__('Data is cached to keep the FPL Draft API happy.', 'draft-league-hub') . '</p>';

		return ob_get_clean();
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
		return $data;
	}

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

	private function render_notice() {
		if (empty($_GET['dlh_notice'])) {
			return;
		}

		$notice = sanitize_key(wp_unslash($_GET['dlh_notice']));
		$messages = array(
			'vote_saved' => __('Vote saved. Democracy survives another month.', 'draft-league-hub'),
			'vote_closed' => __('That monthly vote is closed.', 'draft-league-hub'),
			'sidebet_saved' => __('Sidebet added. Receipts have been filed.', 'draft-league-hub'),
			'poll_saved' => __('Availability poll created.', 'draft-league-hub'),
			'poll_response_saved' => __('Availability saved.', 'draft-league-hub'),
			'login_required' => __('Please log in first.', 'draft-league-hub'),
			'missing_fields' => __('A required field is missing or invalid.', 'draft-league-hub'),
			'save_failed' => __('Could not save that. Try again in a minute.', 'draft-league-hub'),
			'invalid_vote' => __('That vote could not be found.', 'draft-league-hub'),
			'invalid_poll' => __('That poll could not be found.', 'draft-league-hub'),
		);

		if (isset($messages[$notice])) {
			echo '<div class="dlh-notice">' . esc_html($messages[$notice]) . '</div>';
		}
	}
}

DLH_Plugin::instance();
register_activation_hook(__FILE__, array('DLH_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('DLH_Plugin', 'deactivate'));
