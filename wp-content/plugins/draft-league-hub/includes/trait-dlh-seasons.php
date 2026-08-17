<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Seasons {


	public function maybe_upgrade_schema() {
		if (self::SCHEMA_VERSION !== get_option('dlh_schema_version')) {
			$this->install_schema();
		}

		$this->maybe_migrate_legacy_season();
	}


	public function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $this->seasons_table_name();
		$events_table = $this->group_pick_events_table_name();
		$entries_table = $this->group_pick_entries_table_name();
		$cups_table = $this->draft_cups_table_name();
		$ties_table = $this->draft_cup_ties_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(20) NOT NULL,
			slug varchar(40) NOT NULL,
			league_id varchar(32) NOT NULL DEFAULT '',
			status varchar(12) NOT NULL DEFAULT 'archived',
			snapshot longtext NULL,
			snapshot_hash char(64) NOT NULL DEFAULT '',
			snapshot_captured_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset_collate};";

		dbDelta($sql);
		$events_sql = "CREATE TABLE {$events_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			season_id bigint(20) unsigned NOT NULL,
			title varchar(190) NOT NULL,
			event_date date NOT NULL,
			gameweek tinyint(3) unsigned DEFAULT NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY season_date (season_id, event_date),
			KEY event_date (event_date)
		) {$charset_collate};";
		$entries_sql = "CREATE TABLE {$entries_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			manager_id bigint(20) unsigned NOT NULL,
			pick_text text NOT NULL,
			result varchar(12) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_manager (event_id, manager_id),
			KEY manager_result (manager_id, result),
			KEY result (result)
		) {$charset_collate};";
		$cups_sql = "CREATE TABLE {$cups_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			season_id bigint(20) unsigned NOT NULL,
			name varchar(190) NOT NULL,
			start_gameweek tinyint(3) unsigned NOT NULL,
			status varchar(12) NOT NULL DEFAULT 'scheduled',
			champion_manager_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY season_id (season_id),
			KEY status (status)
		) {$charset_collate};";
		$ties_sql = "CREATE TABLE {$ties_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cup_id bigint(20) unsigned NOT NULL,
			round_number tinyint(3) unsigned NOT NULL,
			match_number tinyint(3) unsigned NOT NULL,
			gameweek tinyint(3) unsigned NOT NULL,
			manager_a_id bigint(20) unsigned DEFAULT NULL,
			manager_b_id bigint(20) unsigned DEFAULT NULL,
			source_a_tie_id bigint(20) unsigned DEFAULT NULL,
			source_b_tie_id bigint(20) unsigned DEFAULT NULL,
			score_a smallint(6) DEFAULT NULL,
			score_b smallint(6) DEFAULT NULL,
			winner_manager_id bigint(20) unsigned DEFAULT NULL,
			status varchar(12) NOT NULL DEFAULT 'scheduled',
			score_updated_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY cup_round_match (cup_id, round_number, match_number),
			KEY cup_gameweek (cup_id, gameweek),
			KEY source_a_tie_id (source_a_tie_id),
			KEY source_b_tie_id (source_b_tie_id)
		) {$charset_collate};";

		dbDelta($cups_sql);
		dbDelta($ties_sql);
		dbDelta($events_sql);
		dbDelta($entries_sql);
		$this->seasons_table_ready = null;
		if ($this->seasons_table_exists() && $this->group_pick_tables_exist() && $this->draft_cup_tables_exist()) {
			update_option('dlh_schema_version', self::SCHEMA_VERSION);
		}
	}


	public function maybe_migrate_legacy_season() {
		global $wpdb;

		if (!$this->seasons_table_exists()) {
			return;
		}

		$table_name = $this->seasons_table_name();
		$season_count = absint($wpdb->get_var("SELECT COUNT(*) FROM {$table_name}")); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ($season_count) {
			return;
		}

		$stored_options = get_option(self::OPTION, array());
		$stored_options = is_array($stored_options) ? $stored_options : array();
		$legacy_options = array_merge($this->defaults(), $stored_options);
		$label = $this->sanitize_season_label($legacy_options['season_label'] ?? '');
		$league_id = $this->sanitize_league_id($legacy_options['fpl_league_id'] ?? '');

		if (!$label) {
			return;
		}

		$this->insert_season($label, $league_id, 'current');
	}


	private function seasons_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dlh_seasons';
	}


	private function seasons_table_exists() {
		global $wpdb;

		if (null !== $this->seasons_table_ready) {
			return $this->seasons_table_ready;
		}

		$table_name = $this->seasons_table_name();
		$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
		$this->seasons_table_ready = $table_name === $found;
		return $this->seasons_table_ready;
	}


	private function sanitize_season_label($label) {
		$label = sanitize_text_field((string) $label);
		return substr(trim($label), 0, 20);
	}


	private function sanitize_league_id($league_id) {
		return substr(preg_replace('/[^0-9]/', '', (string) $league_id), 0, 32);
	}


	private function season_slug($label) {
		$slug = sanitize_title(str_replace('/', '-', $label));
		return $slug ? substr($slug, 0, 40) : 'season-' . time();
	}


	private function get_current_season() {
		global $wpdb;

		if (!$this->seasons_table_exists()) {
			return null;
		}

		$table_name = $this->seasons_table_name();
		return $wpdb->get_row(
			"SELECT id, label, slug, league_id, status, snapshot_hash, snapshot_captured_at, created_at, updated_at
			FROM {$table_name}
			WHERE status = 'current'
			ORDER BY id DESC
			LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}


	private function get_seasons() {
		global $wpdb;

		if (!$this->seasons_table_exists()) {
			return array();
		}

		$table_name = $this->seasons_table_name();
		$seasons = $wpdb->get_results(
			"SELECT id, label, slug, league_id, status, snapshot_hash, snapshot_captured_at, created_at, updated_at
			FROM {$table_name}
			ORDER BY CASE WHEN status = 'current' THEN 0 ELSE 1 END, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array($seasons) ? $seasons : array();
	}


	private function get_season_snapshot($season_id) {
		global $wpdb;

		$season_id = absint($season_id);
		if (!$season_id || !$this->seasons_table_exists()) {
			return array();
		}

		$table_name = $this->seasons_table_name();
		$encoded = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT snapshot FROM {$table_name} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$season_id
			)
		);

		if (!$encoded) {
			return array();
		}

		$snapshot = json_decode($encoded, true);
		return is_array($snapshot) ? $snapshot : array();
	}


	private function insert_season($label, $league_id, $status = 'archived') {
		global $wpdb;

		$label = $this->sanitize_season_label($label);
		$league_id = $this->sanitize_league_id($league_id);
		$status = 'current' === $status ? 'current' : 'archived';
		$slug = $this->season_slug($label);

		if (!$label) {
			return new WP_Error('dlh_season_label_required', __('Add a season label before saving.', 'draft-league-hub'));
		}

		$table_name = $this->seasons_table_name();
		$existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE slug = %s LIMIT 1", $slug)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ($existing_id) {
			return new WP_Error('dlh_season_exists', __('That season already exists.', 'draft-league-hub'));
		}

		$now = current_time('mysql', true);
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'label' => $label,
				'slug' => $slug,
				'league_id' => $league_id,
				'status' => $status,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%s', '%s', '%s', '%s', '%s', '%s')
		);

		if (!$inserted) {
			return new WP_Error('dlh_season_save_failed', __('The season could not be saved.', 'draft-league-hub'));
		}

		return absint($wpdb->insert_id);
	}


	private function sync_current_season_from_options($options) {
		global $wpdb;

		if (!$this->seasons_table_exists()) {
			return true;
		}

		$label = $this->sanitize_season_label($options['season_label'] ?? '');
		$league_id = $this->sanitize_league_id($options['fpl_league_id'] ?? '');
		if (!$label) {
			return new WP_Error('dlh_season_label_required', __('Add a current season label before saving.', 'draft-league-hub'));
		}

		$current = $this->get_current_season();
		if (!$current) {
			return $this->insert_season($label, $league_id, 'current');
		}

		$identity_changed = $label !== $current['label'] || $league_id !== $current['league_id'];
		if ($identity_changed && !empty($current['snapshot_captured_at'])) {
			return new WP_Error(
				'dlh_season_is_locked',
				__('This season already has saved data. Use the season rollover form so that data is archived safely.', 'draft-league-hub')
			);
		}

		$slug = $this->season_slug($label);
		$table_name = $this->seasons_table_name();
		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE slug = %s AND id != %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$slug,
				absint($current['id'])
			)
		);

		if ($duplicate) {
			return new WP_Error('dlh_season_exists', __('That season already exists.', 'draft-league-hub'));
		}

		$updated = $wpdb->update(
			$table_name,
			array(
				'label' => $label,
				'slug' => $slug,
				'league_id' => $league_id,
				'updated_at' => current_time('mysql', true),
			),
			array('id' => absint($current['id'])),
			array('%s', '%s', '%s', '%s'),
			array('%d')
		);

		if (false === $updated) {
			return new WP_Error('dlh_season_save_failed', __('The current season could not be updated.', 'draft-league-hub'));
		}

		return true;
	}


	private function record_current_season_snapshot($details, $transactions = array(), $trades = array(), $bootstrap = array(), $draft = array(), $warnings = array()) {
		global $wpdb;

		$current = $this->get_current_season();
		if (!$current) {
			return new WP_Error('dlh_no_current_season', __('No current season is configured.', 'draft-league-hub'));
		}

		$captured_at = current_time('mysql', true);
		$snapshot_content = array(
			'version' => 1,
			'season' => array(
				'label' => $current['label'],
				'league_id' => $current['league_id'],
			),
			'details' => is_array($details) ? $details : array(),
			'transactions' => is_array($transactions) ? $transactions : array(),
			'trades' => is_array($trades) ? $trades : array(),
			'bootstrap' => is_array($bootstrap) ? $bootstrap : array(),
			'draft' => is_array($draft) ? $draft : array(),
			'warnings' => array_values(array_filter(array_map('sanitize_text_field', (array) $warnings))),
		);
		$content_encoded = wp_json_encode($snapshot_content);
		$snapshot = array_merge(array('captured_at' => $captured_at), $snapshot_content);
		$encoded = wp_json_encode($snapshot);

		if (!$content_encoded || !$encoded) {
			return new WP_Error('dlh_snapshot_encode_failed', __('The season data could not be prepared for storage.', 'draft-league-hub'));
		}

		$hash = hash('sha256', $content_encoded);
		$table_name = $this->seasons_table_name();
		$data = array(
			'snapshot_captured_at' => $captured_at,
			'updated_at' => $captured_at,
		);
		$formats = array('%s', '%s');

		if ($hash !== $current['snapshot_hash']) {
			$data['snapshot'] = $encoded;
			$data['snapshot_hash'] = $hash;
			$formats[] = '%s';
			$formats[] = '%s';
		}

		$updated = $wpdb->update(
			$table_name,
			$data,
			array('id' => absint($current['id'])),
			$formats,
			array('%d')
		);

		if (false === $updated) {
			return new WP_Error('dlh_snapshot_save_failed', __('The season snapshot could not be saved.', 'draft-league-hub'));
		}

		return true;
	}


	private function capture_current_season_snapshot() {
		$current = $this->get_current_season();
		if (!$current || empty($current['league_id'])) {
			return new WP_Error('dlh_season_league_required', __('Add the current season FPL Draft league ID before capturing it.', 'draft-league-hub'));
		}

		$league_id = rawurlencode($current['league_id']);
		$details = $this->api_get('/api/league/' . $league_id . '/details');
		if (is_wp_error($details)) {
			return $details;
		}

		$warnings = array();
		$saved_snapshot = $this->get_season_snapshot($current['id']);
		$transactions = $this->api_get('/api/draft/league/' . $league_id . '/transactions');
		if (is_wp_error($transactions)) {
			$warnings[] = $transactions->get_error_message();
			$transactions = $saved_snapshot['transactions'] ?? array();
		}

		$trades = $this->api_get('/api/draft/league/' . $league_id . '/trades');
		if (is_wp_error($trades)) {
			$warnings[] = $trades->get_error_message();
			$trades = $saved_snapshot['trades'] ?? array();
		}

		$bootstrap = $this->api_get('/api/bootstrap-static');
		if (is_wp_error($bootstrap)) {
			$warnings[] = $bootstrap->get_error_message();
			$bootstrap = $saved_snapshot['bootstrap'] ?? array();
		}

		$draft = $this->api_get('/api/draft/' . $league_id . '/choices');
		if (is_wp_error($draft)) {
			$warnings[] = $draft->get_error_message();
			$draft = $saved_snapshot['draft'] ?? array();
		}

		return $this->record_current_season_snapshot($details, $transactions, $trades, $bootstrap, $draft, $warnings);
	}


	private function sync_current_season_managers() {
		$current = $this->get_current_season();
		if (!$current || empty($current['league_id'])) {
			return new WP_Error('dlh_season_league_required', __('Add the current season FPL Draft league ID before syncing managers.', 'draft-league-hub'));
		}

		$details = $this->api_get('/api/league/' . rawurlencode($current['league_id']) . '/details');
		if (is_wp_error($details)) {
			return $details;
		}

		$entries = $details['league_entries'] ?? array();
		if (!$entries || !is_array($entries)) {
			return new WP_Error('dlh_no_league_entries', __('No managers were returned for the current league.', 'draft-league-hub'));
		}

		$created = 0;
		$updated = 0;
		foreach ($entries as $entry) {
			$entry_id = absint($entry['entry_id'] ?? 0);
			$league_entry_id = absint($entry['id'] ?? 0);
			$team_name = sanitize_text_field($entry['entry_name'] ?? '');
			$real_name = sanitize_text_field(trim(($entry['player_first_name'] ?? '') . ' ' . ($entry['player_last_name'] ?? '')));
			if (!$entry_id || !$team_name) {
				continue;
			}

			$manager_ids = get_posts(
				array(
					'post_type' => 'dlh_manager',
					'post_status' => 'any',
					'posts_per_page' => 1,
					'fields' => 'ids',
					'meta_key' => 'dlh_fpl_entry_id',
					'meta_value' => (string) $entry_id,
				)
			);

			if (!$manager_ids) {
				$manager_ids = get_posts(
					array(
						'post_type' => 'dlh_manager',
						'post_status' => 'any',
						'posts_per_page' => 1,
						'fields' => 'ids',
						'meta_key' => 'dlh_team_name',
						'meta_value' => $team_name,
					)
				);
			}

			if (!$manager_ids && $real_name) {
				$name_matches = get_posts(
					array(
						'post_type' => 'dlh_manager',
						'post_status' => 'any',
						'posts_per_page' => 2,
						'fields' => 'ids',
						'meta_key' => 'dlh_real_name',
						'meta_value' => $real_name,
					)
				);
				if (1 === count($name_matches)) {
					$manager_ids = $name_matches;
				}
			}

			$manager_id = absint($manager_ids[0] ?? 0);
			if (!$manager_id) {
				$manager_id = wp_insert_post(
					array(
						'post_type' => 'dlh_manager',
						'post_status' => 'publish',
						'post_title' => $real_name ? $real_name : $team_name,
					),
					true
				);

				if (is_wp_error($manager_id)) {
					return $manager_id;
				}
				$created++;
			} else {
				$updated++;
			}

			update_post_meta($manager_id, 'dlh_real_name', $real_name);
			update_post_meta($manager_id, 'dlh_team_name', $team_name);
			update_post_meta($manager_id, 'dlh_fpl_entry_id', $entry_id);
			update_post_meta($manager_id, 'dlh_fpl_league_entry_id', $league_entry_id);
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'total' => $created + $updated,
		);
	}


	private function reset_current_season($new_league_id, $confirmation) {
		global $wpdb;

		if (!current_user_can('manage_options')) {
			return new WP_Error('dlh_reset_permission_denied', __('You do not have permission to reset the current season.', 'draft-league-hub'));
		}

		$current = $this->get_current_season();
		if (!$current) {
			return new WP_Error('dlh_no_current_season', __('No current season is configured.', 'draft-league-hub'));
		}

		$new_league_id = $this->sanitize_league_id($new_league_id);
		if (!$new_league_id) {
			return new WP_Error('dlh_reset_league_required', __('Add the replacement FPL Draft league ID before resetting the season.', 'draft-league-hub'));
		}

		$expected_confirmation = 'RESET ' . $current['label'];
		$confirmation = trim(sanitize_text_field((string) $confirmation));
		if (!hash_equals($expected_confirmation, $confirmation)) {
			return new WP_Error(
				'dlh_reset_confirmation_failed',
				sprintf(__('Confirmation did not match. Type %s exactly.', 'draft-league-hub'), $expected_confirmation)
			);
		}

		$season_id = absint($current['id']);
		$seasons_table = $this->seasons_table_name();
		$events_table = $this->group_pick_events_table_name();
		$entries_table = $this->group_pick_entries_table_name();
		$cups_table = $this->draft_cups_table_name();
		$ties_table = $this->draft_cup_ties_table_name();
		$now = current_time('mysql', true);

		if (false === $wpdb->query('START TRANSACTION')) {
			return new WP_Error('dlh_reset_failed', __('The season reset could not start, so no season data was removed.', 'draft-league-hub'));
		}

		$pick_entries = $wpdb->query(
			$wpdb->prepare(
				"DELETE entries FROM {$entries_table} entries INNER JOIN {$events_table} events ON events.id = entries.event_id WHERE events.season_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$season_id
			)
		);
		$pick_events = false === $pick_entries ? false : $wpdb->delete($events_table, array('season_id' => $season_id), array('%d'));
		$cup_ties = false === $pick_events ? false : $wpdb->query(
			$wpdb->prepare(
				"DELETE ties FROM {$ties_table} ties INNER JOIN {$cups_table} cups ON cups.id = ties.cup_id WHERE cups.season_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$season_id
			)
		);
		$cups = false === $cup_ties ? false : $wpdb->delete($cups_table, array('season_id' => $season_id), array('%d'));
		$season_updated = false === $cups ? false : $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$seasons_table}
				SET league_id = %s, snapshot = NULL, snapshot_hash = '', snapshot_captured_at = NULL, updated_at = %s
				WHERE id = %d AND status = 'current'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$new_league_id,
				$now,
				$season_id
			)
		);

		if (false === $pick_entries || false === $pick_events || false === $cup_ties || false === $cups || false === $season_updated) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('dlh_reset_failed', __('The season reset failed, so no season data was removed.', 'draft-league-hub'));
		}

		if (false === $wpdb->query('COMMIT')) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('dlh_reset_failed', __('The season reset could not be committed, so no season data was removed.', 'draft-league-hub'));
		}

		$options = get_option(self::OPTION, array());
		$options = is_array($options) ? $options : array();
		$options['season_label'] = $current['label'];
		$options['fpl_league_id'] = $new_league_id;
		update_option(self::OPTION, $options);
		$cache_paths = array('/api/bootstrap-static');
		foreach (array_unique(array_filter(array($current['league_id'], $new_league_id))) as $league_id) {
			$encoded_league_id = rawurlencode($league_id);
			$cache_paths[] = '/api/league/' . $encoded_league_id . '/details';
			$cache_paths[] = '/api/draft/league/' . $encoded_league_id . '/transactions';
			$cache_paths[] = '/api/draft/league/' . $encoded_league_id . '/trades';
			$cache_paths[] = '/api/draft/' . $encoded_league_id . '/choices';
		}
		$cleared_transients = $this->clear_api_cache($cache_paths);

		return array(
			'season_id' => $season_id,
			'season_label' => $current['label'],
			'league_id' => $new_league_id,
			'pick_entries' => absint($pick_entries),
			'pick_events' => absint($pick_events),
			'cup_ties' => absint($cup_ties),
			'cups' => absint($cups),
			'cleared_transients' => absint($cleared_transients),
		);
	}


	private function rollover_current_season($new_label, $new_league_id) {
		global $wpdb;

		$new_label = $this->sanitize_season_label($new_label);
		$new_league_id = $this->sanitize_league_id($new_league_id);
		if (!$new_label) {
			return new WP_Error('dlh_season_label_required', __('Add the new season label before rolling over.', 'draft-league-hub'));
		}

		$current = $this->get_current_season();
		if (!$current) {
			return new WP_Error('dlh_no_current_season', __('No current season is configured.', 'draft-league-hub'));
		}

		$snapshot_result = $this->capture_current_season_snapshot();
		if (is_wp_error($snapshot_result)) {
			return new WP_Error(
				'dlh_rollover_capture_failed',
				sprintf(__('Rollover stopped because the current season could not be captured: %s', 'draft-league-hub'), $snapshot_result->get_error_message())
			);
		}

		$table_name = $this->seasons_table_name();
		$new_slug = $this->season_slug($new_label);
		$duplicate = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE slug = %s LIMIT 1", $new_slug)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ($duplicate) {
			return new WP_Error('dlh_season_exists', __('That season already exists.', 'draft-league-hub'));
		}

		$now = current_time('mysql', true);
		$wpdb->query('START TRANSACTION');
		$archived = $wpdb->update(
			$table_name,
			array(
				'status' => 'archived',
				'updated_at' => $now,
			),
			array('id' => absint($current['id'])),
			array('%s', '%s'),
			array('%d')
		);

		if (false === $archived) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('dlh_rollover_failed', __('The current season could not be archived.', 'draft-league-hub'));
		}

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'label' => $new_label,
				'slug' => $new_slug,
				'league_id' => $new_league_id,
				'status' => 'current',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%s', '%s', '%s', '%s', '%s', '%s')
		);

		if (!$inserted) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('dlh_rollover_failed', __('The new season could not be created, so no changes were made.', 'draft-league-hub'));
		}

		$wpdb->query('COMMIT');

		$options = get_option(self::OPTION, array());
		$options = is_array($options) ? $options : array();
		$options['season_label'] = $new_label;
		$options['fpl_league_id'] = $new_league_id;
		update_option(self::OPTION, $options);

		return true;
	}


	private function suggested_next_season_label($current_label) {
		if (preg_match('/^(\d{4})\/(\d{2})$/', (string) $current_label, $matches)) {
			$start = absint($matches[1]) + 1;
			return sprintf('%d/%02d', $start, ($start + 1) % 100);
		}

		if (preg_match('/^(\d{4})\/(\d{4})$/', (string) $current_label, $matches)) {
			return sprintf('%d/%d', absint($matches[1]) + 1, absint($matches[2]) + 1);
		}

		return '';
	}
}
