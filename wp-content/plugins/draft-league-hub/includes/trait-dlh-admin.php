<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Admin {


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
}
