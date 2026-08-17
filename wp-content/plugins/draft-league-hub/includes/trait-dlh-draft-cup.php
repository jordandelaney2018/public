<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Draft_Cup {


	private function draft_cups_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dlh_cups';
	}


	private function draft_cup_ties_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dlh_cup_ties';
	}


	private function draft_cup_tables_exist() {
		global $wpdb;

		$cups_table = $this->draft_cups_table_name();
		$ties_table = $this->draft_cup_ties_table_name();
		$cups_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cups_table));
		$ties_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ties_table));

		return $cups_table === $cups_found && $ties_table === $ties_found;
	}


	private function draft_cup_round_labels() {
		return array(
			1 => __('Opening round', 'draft-league-hub'),
			2 => __('Quarter-finals', 'draft-league-hub'),
			3 => __('Semi-finals', 'draft-league-hub'),
			4 => __('Final', 'draft-league-hub'),
		);
	}


	private function draft_cup_status_labels() {
		return array(
			'scheduled' => __('Scheduled', 'draft-league-hub'),
			'live' => __('Live', 'draft-league-hub'),
			'tied' => __('Tie-break needed', 'draft-league-hub'),
			'complete' => __('Complete', 'draft-league-hub'),
		);
	}


	public function register_draft_cup_admin_page() {
		add_submenu_page(
			'edit.php?post_type=dlh_manager',
			__('Draft Cup', 'draft-league-hub'),
			__('Draft Cup', 'draft-league-hub'),
			'manage_options',
			'dlh-draft-cup',
			array($this, 'render_draft_cup_admin_page')
		);
	}


	private function draft_cup_admin_url($args = array()) {
		return add_query_arg(
			array_merge(array('post_type' => 'dlh_manager', 'page' => 'dlh-draft-cup'), $args),
			admin_url('edit.php')
		);
	}


	public function render_draft_cup_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$notice = '';
		$notice_type = 'success';
		if (isset($_POST['dlh_create_draft_cup'])) {
			check_admin_referer('dlh_create_draft_cup');
			$result = $this->create_draft_cup_from_request();
			if (is_wp_error($result)) {
				$notice = $result->get_error_message();
				$notice_type = 'error';
			} else {
				wp_safe_redirect($this->draft_cup_admin_url(array('created' => 1)));
				exit;
			}
		}

		$current_season = $this->get_current_season();
		$cup = $current_season ? $this->get_draft_cup_for_season($current_season['id']) : null;

		if ($cup && isset($_POST['dlh_refresh_draft_cup'])) {
			check_admin_referer('dlh_refresh_draft_cup');
			$result = $this->refresh_draft_cup_scores($cup['id']);
			if (is_wp_error($result)) {
				$notice = $result->get_error_message();
				$notice_type = 'error';
			} else {
				$notice = sprintf(
					__('FPL score refresh finished: %1$d ties updated and %2$d completed.', 'draft-league-hub'),
					absint($result['updated']),
					absint($result['completed'])
				);
				if (!empty($result['warnings'])) {
					$notice .= ' ' . sprintf(
						_n('%d score could not be fetched; use the manual fields if needed.', '%d scores could not be fetched; use the manual fields if needed.', count($result['warnings']), 'draft-league-hub'),
						count($result['warnings'])
					);
					$notice_type = 'warning';
				}
				$cup = $this->get_draft_cup_for_season($current_season['id']);
			}
		}

		if ($cup && isset($_POST['dlh_save_draft_cup_scores'])) {
			check_admin_referer('dlh_save_draft_cup_scores');
			$result = $this->save_draft_cup_manual_results($cup['id']);
			if (is_wp_error($result)) {
				$notice = $result->get_error_message();
				$notice_type = 'error';
			} else {
				wp_safe_redirect($this->draft_cup_admin_url(array('saved' => 1)));
				exit;
			}
		}

		$managers = $this->get_managers();
		$ties = $cup ? $this->get_draft_cup_ties($cup['id']) : array();
		$round_labels = $this->draft_cup_round_labels();
		$status_labels = $this->draft_cup_status_labels();
		?>
		<div class="wrap dlh-cup-admin">
			<h1><?php echo esc_html__('Draft Cup', 'draft-league-hub'); ?></h1>
			<p><?php echo esc_html__('A four-gameweek knockout cup for all 12 managers: four random byes, four opening ties, quarter-finals, semi-finals, and the final.', 'draft-league-hub'); ?></p>

			<?php if (!empty($_GET['created'])) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__('The random draw is complete and the bracket is live.', 'draft-league-hub'); ?></p></div><?php endif; ?>
			<?php if (!empty($_GET['saved'])) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Cup scores and winners were saved.', 'draft-league-hub'); ?></p></div><?php endif; ?>
			<?php if ($notice) : ?><div class="notice notice-<?php echo esc_attr($notice_type); ?>"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>

			<?php if (!$current_season) : ?>
				<div class="notice notice-error"><p><?php echo esc_html__('Set up the current season in Settings > Draft League Hub before creating the cup.', 'draft-league-hub'); ?></p></div>
			<?php elseif (!$cup) : ?>
				<div class="dlh-cup-admin__setup">
					<h2><?php echo esc_html(sprintf(__('Create the %s cup', 'draft-league-hub'), $current_season['label'])); ?></h2>
					<?php if (12 !== count($managers)) : ?>
						<div class="notice notice-error inline"><p><?php echo esc_html(sprintf(__('The draw needs exactly 12 published managers. There are currently %d.', 'draft-league-hub'), count($managers))); ?></p></div>
					<?php endif; ?>
					<form method="post" action="">
						<?php wp_nonce_field('dlh_create_draft_cup'); ?>
						<input type="hidden" name="season_id" value="<?php echo esc_attr($current_season['id']); ?>">
						<table class="form-table" role="presentation">
							<tr><th scope="row"><label for="dlh_cup_name"><?php echo esc_html__('Cup name', 'draft-league-hub'); ?></label></th><td><input class="regular-text" id="dlh_cup_name" name="cup_name" type="text" maxlength="190" value="<?php echo esc_attr($current_season['label'] . ' Draft Cup'); ?>" required></td></tr>
							<tr><th scope="row"><label for="dlh_cup_start_gameweek"><?php echo esc_html__('Starting gameweek', 'draft-league-hub'); ?></label></th><td><input id="dlh_cup_start_gameweek" name="start_gameweek" type="number" min="1" max="35" required><p class="description"><?php echo esc_html__('The final is three gameweeks later, so the latest possible start is Gameweek 35.', 'draft-league-hub'); ?></p></td></tr>
						</table>
						<p><strong><?php echo esc_html__('The draw cannot be predicted or reloaded:', 'draft-league-hub'); ?></strong> <?php echo esc_html__('all 12 managers are shuffled once, with four randomly receiving opening-round byes.', 'draft-league-hub'); ?></p>
						<?php submit_button(__('Create random draw', 'draft-league-hub'), 'primary', 'dlh_create_draft_cup', false, 12 === count($managers) ? array() : array('disabled' => 'disabled')); ?>
					</form>
				</div>
			<?php else : ?>
				<div class="dlh-cup-admin__summary">
					<div><span><?php echo esc_html__('Competition', 'draft-league-hub'); ?></span><strong><?php echo esc_html($cup['name']); ?></strong></div>
					<div><span><?php echo esc_html__('Gameweeks', 'draft-league-hub'); ?></span><strong><?php echo esc_html(sprintf('%d–%d', absint($cup['start_gameweek']), absint($cup['start_gameweek']) + 3)); ?></strong></div>
					<div><span><?php echo esc_html__('Status', 'draft-league-hub'); ?></span><strong><?php echo esc_html($status_labels[$cup['status']] ?? ucfirst($cup['status'])); ?></strong></div>
					<div><span><?php echo esc_html__('Champion', 'draft-league-hub'); ?></span><strong><?php echo esc_html($cup['champion_manager_id'] ? $this->manager_name($cup['champion_manager_id']) : '—'); ?></strong></div>
				</div>

				<form method="post" action="" class="dlh-cup-admin__refresh">
					<?php wp_nonce_field('dlh_refresh_draft_cup'); ?>
					<?php submit_button(__('Refresh scores from FPL', 'draft-league-hub'), 'secondary', 'dlh_refresh_draft_cup', false); ?>
					<span class="description"><?php echo esc_html__('Winners are only advanced automatically once the FPL gameweek is finished.', 'draft-league-hub'); ?></span>
				</form>

				<form method="post" action="" class="dlh-cup-admin__scores">
					<?php wp_nonce_field('dlh_save_draft_cup_scores'); ?>
					<h2><?php echo esc_html__('Scores and tie-breaks', 'draft-league-hub'); ?></h2>
					<p><?php echo esc_html__('Use these fields if an FPL score is unavailable. The higher score advances automatically; for equal scores, choose the tie-break winner.', 'draft-league-hub'); ?></p>
					<div class="dlh-cup-admin__table-wrap">
						<table class="widefat striped dlh-cup-admin__table">
							<thead><tr><th><?php echo esc_html__('Round', 'draft-league-hub'); ?></th><th><?php echo esc_html__('GW', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Team A', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Score', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Team B', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Score', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Winner / tie-break', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Status', 'draft-league-hub'); ?></th></tr></thead>
							<tbody>
							<?php foreach ($ties as $tie) : ?>
								<?php $has_teams = !empty($tie['manager_a_id']) && !empty($tie['manager_b_id']); ?>
								<tr>
									<td><strong><?php echo esc_html($round_labels[$tie['round_number']] ?? ''); ?> <?php echo esc_html($tie['match_number']); ?></strong></td>
									<td><?php echo esc_html($tie['gameweek']); ?></td>
									<td><?php echo esc_html($this->draft_cup_admin_slot_name($tie, 'a')); ?></td>
									<td><input type="number" name="score_a[<?php echo esc_attr($tie['id']); ?>]" min="-999" max="999" value="<?php echo esc_attr(null === $tie['score_a'] ? '' : $tie['score_a']); ?>" <?php disabled(!$has_teams); ?>></td>
									<td><?php echo esc_html($this->draft_cup_admin_slot_name($tie, 'b')); ?></td>
									<td><input type="number" name="score_b[<?php echo esc_attr($tie['id']); ?>]" min="-999" max="999" value="<?php echo esc_attr(null === $tie['score_b'] ? '' : $tie['score_b']); ?>" <?php disabled(!$has_teams); ?>></td>
									<td><select name="winner[<?php echo esc_attr($tie['id']); ?>]" <?php disabled(!$has_teams); ?>><option value="0"><?php echo esc_html__('Automatic / unresolved', 'draft-league-hub'); ?></option><?php if ($has_teams) : ?><option value="<?php echo esc_attr($tie['manager_a_id']); ?>" <?php selected($tie['winner_manager_id'], $tie['manager_a_id']); ?>><?php echo esc_html($this->manager_name($tie['manager_a_id'])); ?></option><option value="<?php echo esc_attr($tie['manager_b_id']); ?>" <?php selected($tie['winner_manager_id'], $tie['manager_b_id']); ?>><?php echo esc_html($this->manager_name($tie['manager_b_id'])); ?></option><?php endif; ?></select></td>
									<td><span class="dlh-cup-admin__status dlh-cup-admin__status--<?php echo esc_attr($tie['status']); ?>"><?php echo esc_html($status_labels[$tie['status']] ?? ucfirst($tie['status'])); ?></span></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php submit_button(__('Save scores and advance winners', 'draft-league-hub'), 'primary', 'dlh_save_draft_cup_scores'); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}


	private function create_draft_cup_from_request() {
		$season_id = absint($_POST['season_id'] ?? 0);
		$name = sanitize_text_field(wp_unslash($_POST['cup_name'] ?? ''));
		$start_gameweek = absint($_POST['start_gameweek'] ?? 0);
		return $this->create_draft_cup($season_id, $name, $start_gameweek);
	}


	private function create_draft_cup($season_id, $name, $start_gameweek) {
		global $wpdb;

		if (!current_user_can('manage_options')) {
			return new WP_Error('dlh_cup_permission_denied', __('You do not have permission to create the Draft Cup.', 'draft-league-hub'));
		}

		$season_id = absint($season_id);
		$name = substr(sanitize_text_field($name), 0, 190);
		$start_gameweek = absint($start_gameweek);
		$season_ids = array_map('absint', wp_list_pluck($this->get_seasons(), 'id'));
		if (!$season_id || !in_array($season_id, $season_ids, true) || !$name) {
			return new WP_Error('dlh_cup_required_fields', __('Choose a valid season and add a cup name.', 'draft-league-hub'));
		}
		if ($start_gameweek < 1 || $start_gameweek > 35) {
			return new WP_Error('dlh_cup_invalid_gameweek', __('The cup must start between Gameweek 1 and Gameweek 35.', 'draft-league-hub'));
		}
		if ($this->get_draft_cup_for_season($season_id)) {
			return new WP_Error('dlh_cup_already_exists', __('This season already has a Draft Cup.', 'draft-league-hub'));
		}

		$manager_ids = array_map('absint', wp_list_pluck($this->get_managers(), 'ID'));
		if (12 !== count($manager_ids)) {
			return new WP_Error('dlh_cup_manager_count', sprintf(__('The draw needs exactly 12 published managers; %d were found.', 'draft-league-hub'), count($manager_ids)));
		}
		for ($index = count($manager_ids) - 1; $index > 0; $index--) {
			$swap_index = random_int(0, $index);
			$temp = $manager_ids[$index];
			$manager_ids[$index] = $manager_ids[$swap_index];
			$manager_ids[$swap_index] = $temp;
		}

		$cups_table = $this->draft_cups_table_name();
		$now = current_time('mysql', true);
		$wpdb->query('START TRANSACTION');
		$created = $wpdb->insert(
			$cups_table,
			array(
				'season_id' => $season_id,
				'name' => $name,
				'start_gameweek' => $start_gameweek,
				'status' => 'scheduled',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%d', '%s', '%d', '%s', '%s', '%s')
		);
		$cup_id = absint($wpdb->insert_id);
		if (!$created || !$cup_id) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('dlh_cup_create_failed', __('The Draft Cup could not be created.', 'draft-league-hub'));
		}

		$opening_ids = array();
		for ($match = 1; $match <= 4; $match++) {
			$tie_id = $this->insert_draft_cup_tie(
				$cup_id,
				1,
				$match,
				$start_gameweek,
				$manager_ids[($match - 1) * 2],
				$manager_ids[(($match - 1) * 2) + 1]
			);
			if (is_wp_error($tie_id)) {
				$wpdb->query('ROLLBACK');
				return $tie_id;
			}
			$opening_ids[] = $tie_id;
		}

		$quarter_final_ids = array();
		for ($match = 1; $match <= 4; $match++) {
			$tie_id = $this->insert_draft_cup_tie(
				$cup_id,
				2,
				$match,
				$start_gameweek + 1,
				$manager_ids[7 + $match],
				null,
				null,
				$opening_ids[$match - 1]
			);
			if (is_wp_error($tie_id)) {
				$wpdb->query('ROLLBACK');
				return $tie_id;
			}
			$quarter_final_ids[] = $tie_id;
		}

		$semi_final_ids = array(
			$this->insert_draft_cup_tie($cup_id, 3, 1, $start_gameweek + 2, null, null, $quarter_final_ids[0], $quarter_final_ids[1]),
			$this->insert_draft_cup_tie($cup_id, 3, 2, $start_gameweek + 2, null, null, $quarter_final_ids[2], $quarter_final_ids[3]),
		);
		foreach ($semi_final_ids as $tie_id) {
			if (is_wp_error($tie_id)) {
				$wpdb->query('ROLLBACK');
				return $tie_id;
			}
		}
		$final_id = $this->insert_draft_cup_tie($cup_id, 4, 1, $start_gameweek + 3, null, null, $semi_final_ids[0], $semi_final_ids[1]);
		if (is_wp_error($final_id)) {
			$wpdb->query('ROLLBACK');
			return $final_id;
		}

		$wpdb->query('COMMIT');
		return $cup_id;
	}


	private function insert_draft_cup_tie($cup_id, $round_number, $match_number, $gameweek, $manager_a_id = null, $manager_b_id = null, $source_a_tie_id = null, $source_b_tie_id = null) {
		global $wpdb;

		$now = current_time('mysql', true);
		$inserted = $wpdb->insert(
			$this->draft_cup_ties_table_name(),
			array(
				'cup_id' => absint($cup_id),
				'round_number' => absint($round_number),
				'match_number' => absint($match_number),
				'gameweek' => absint($gameweek),
				'manager_a_id' => $manager_a_id ? absint($manager_a_id) : null,
				'manager_b_id' => $manager_b_id ? absint($manager_b_id) : null,
				'source_a_tie_id' => $source_a_tie_id ? absint($source_a_tie_id) : null,
				'source_b_tie_id' => $source_b_tie_id ? absint($source_b_tie_id) : null,
				'status' => 'scheduled',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s')
		);
		if (!$inserted) {
			return new WP_Error('dlh_cup_tie_create_failed', __('A Draft Cup tie could not be created.', 'draft-league-hub'));
		}

		return absint($wpdb->insert_id);
	}


	private function get_draft_cup_for_season($season_id) {
		global $wpdb;
		$table_name = $this->draft_cups_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE season_id = %d LIMIT 1", absint($season_id)), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}


	private function get_draft_cup($cup_id) {
		global $wpdb;
		$table_name = $this->draft_cups_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", absint($cup_id)), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}


	private function get_draft_cup_ties($cup_id) {
		global $wpdb;
		$table_name = $this->draft_cup_ties_table_name();
		$ties = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table_name} WHERE cup_id = %d ORDER BY round_number ASC, match_number ASC", absint($cup_id)), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return is_array($ties) ? $ties : array();
	}


	private function get_draft_cup_tie($tie_id) {
		global $wpdb;
		$table_name = $this->draft_cup_ties_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", absint($tie_id)), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}


	private function get_draft_cup_bootstrap($season_id) {
		global $wpdb;

		$bootstrap = $this->api_get('/api/bootstrap-static');
		if (!is_wp_error($bootstrap)) {
			return $bootstrap;
		}

		$seasons_table = $this->seasons_table_name();
		$snapshot_json = $wpdb->get_var($wpdb->prepare("SELECT snapshot FROM {$seasons_table} WHERE id = %d LIMIT 1", absint($season_id))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$snapshot = json_decode((string) $snapshot_json, true);
		if (is_array($snapshot) && !empty($snapshot['bootstrap']) && is_array($snapshot['bootstrap'])) {
			return $snapshot['bootstrap'];
		}

		return $bootstrap;
	}


	private function draft_cup_event_state($bootstrap, $gameweek) {
		foreach ((array) ($bootstrap['events'] ?? array()) as $event) {
			if (absint($event['id'] ?? 0) !== absint($gameweek)) {
				continue;
			}

			$deadline = strtotime((string) ($event['deadline_time'] ?? ''));
			return array(
				'started' => $deadline && $deadline <= time(),
				'finished' => !empty($event['finished']),
			);
		}

		return array('started' => false, 'finished' => false);
	}


	private function get_draft_cup_manager_score($manager_id, $gameweek) {
		$entry_id = absint(get_post_meta(absint($manager_id), 'dlh_fpl_entry_id', true));
		if (!$entry_id) {
			return new WP_Error('dlh_cup_missing_entry_id', sprintf(__('%s does not have an FPL Draft entry ID.', 'draft-league-hub'), $this->manager_name($manager_id)));
		}

		$data = $this->api_get('/api/entry/' . $entry_id . '/event/' . absint($gameweek));
		if (is_wp_error($data)) {
			return $data;
		}

		$candidates = array(
			$data['entry_history']['points'] ?? null,
			$data['entry_history']['event_points'] ?? null,
			$data['points'] ?? null,
			$data['event_points'] ?? null,
		);
		foreach ($candidates as $candidate) {
			if (is_numeric($candidate)) {
				return (int) $candidate;
			}
		}

		if (!empty($data['picks']) && is_array($data['picks'])) {
			$total = 0;
			$has_points = false;
			foreach ($data['picks'] as $pick) {
				if (isset($pick['points']) && is_numeric($pick['points'])) {
					$total += (int) $pick['points'];
					$has_points = true;
				}
			}
			if ($has_points) {
				return $total;
			}
		}

		return new WP_Error('dlh_cup_score_missing', sprintf(__('FPL did not return a Gameweek %1$d score for %2$s.', 'draft-league-hub'), absint($gameweek), $this->manager_name($manager_id)));
	}


	private function refresh_draft_cup_scores($cup_id) {
		global $wpdb;

		$cup = $this->get_draft_cup($cup_id);
		if (!$cup) {
			return new WP_Error('dlh_cup_missing', __('The Draft Cup could not be found.', 'draft-league-hub'));
		}

		$bootstrap = $this->get_draft_cup_bootstrap($cup['season_id']);
		if (is_wp_error($bootstrap)) {
			return $bootstrap;
		}

		$updated = 0;
		$completed = 0;
		$warnings = array();
		$ties_table = $this->draft_cup_ties_table_name();

		for ($round = 1; $round <= 4; $round++) {
			$this->propagate_draft_cup_winners($cup_id);
			foreach ($this->get_draft_cup_ties($cup_id) as $tie) {
				if (absint($tie['round_number']) !== $round || !empty($tie['winner_manager_id'])) {
					continue;
				}
				if (empty($tie['manager_a_id']) || empty($tie['manager_b_id'])) {
					continue;
				}

				$event_state = $this->draft_cup_event_state($bootstrap, $tie['gameweek']);
				if (!$event_state['started']) {
					continue;
				}

				$score_a = $this->get_draft_cup_manager_score($tie['manager_a_id'], $tie['gameweek']);
				$score_b = $this->get_draft_cup_manager_score($tie['manager_b_id'], $tie['gameweek']);
				if (is_wp_error($score_a) || is_wp_error($score_b)) {
					if (is_wp_error($score_a)) {
						$warnings[] = $score_a->get_error_message();
					}
					if (is_wp_error($score_b)) {
						$warnings[] = $score_b->get_error_message();
					}
					continue;
				}

				$winner_id = null;
				$status = 'live';
				if ($event_state['finished']) {
					if ($score_a > $score_b) {
						$winner_id = absint($tie['manager_a_id']);
						$status = 'complete';
					} elseif ($score_b > $score_a) {
						$winner_id = absint($tie['manager_b_id']);
						$status = 'complete';
					} else {
						$status = 'tied';
					}
				}

				$now = current_time('mysql', true);
				$saved = $wpdb->update(
					$ties_table,
					array(
						'score_a' => $score_a,
						'score_b' => $score_b,
						'winner_manager_id' => $winner_id,
						'status' => $status,
						'score_updated_at' => $now,
						'updated_at' => $now,
					),
					array('id' => absint($tie['id'])),
					array('%d', '%d', '%d', '%s', '%s', '%s'),
					array('%d')
				);
				if (false !== $saved) {
					$updated++;
					if ($winner_id) {
						$completed++;
					}
				}
			}
		}

		$this->propagate_draft_cup_winners($cup_id);
		$this->update_draft_cup_status($cup_id);
		return array('updated' => $updated, 'completed' => $completed, 'warnings' => array_values(array_unique($warnings)));
	}


	private function save_draft_cup_manual_results($cup_id) {
		global $wpdb;

		if (!current_user_can('manage_options')) {
			return new WP_Error('dlh_cup_permission_denied', __('You do not have permission to edit Draft Cup scores.', 'draft-league-hub'));
		}
		$cup = $this->get_draft_cup($cup_id);
		if (!$cup) {
			return new WP_Error('dlh_cup_missing', __('The Draft Cup could not be found.', 'draft-league-hub'));
		}

		$raw_score_a = isset($_POST['score_a']) && is_array($_POST['score_a']) ? wp_unslash($_POST['score_a']) : array();
		$raw_score_b = isset($_POST['score_b']) && is_array($_POST['score_b']) ? wp_unslash($_POST['score_b']) : array();
		$raw_winners = isset($_POST['winner']) && is_array($_POST['winner']) ? wp_unslash($_POST['winner']) : array();
		$ties_table = $this->draft_cup_ties_table_name();
		$updated = 0;
		$wpdb->query('START TRANSACTION');

		foreach ($this->get_draft_cup_ties($cup_id) as $tie) {
			$tie_id = absint($tie['id']);
			if (empty($tie['manager_a_id']) || empty($tie['manager_b_id'])) {
				continue;
			}
			$score_a_raw = sanitize_text_field((string) ($raw_score_a[$tie_id] ?? ''));
			$score_b_raw = sanitize_text_field((string) ($raw_score_b[$tie_id] ?? ''));
			if ('' === $score_a_raw && '' === $score_b_raw) {
				continue;
			}
			if (!preg_match('/^-?\d+$/', $score_a_raw) || !preg_match('/^-?\d+$/', $score_b_raw)) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('dlh_cup_invalid_score', sprintf(__('Add both numeric scores for %1$s tie %2$d.', 'draft-league-hub'), $this->draft_cup_round_labels()[$tie['round_number']], absint($tie['match_number'])));
			}

			$score_a = (int) $score_a_raw;
			$score_b = (int) $score_b_raw;
			if ($score_a < -999 || $score_a > 999 || $score_b < -999 || $score_b > 999) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('dlh_cup_invalid_score', __('Cup scores must be between -999 and 999.', 'draft-league-hub'));
			}

			$chosen_winner = absint($raw_winners[$tie_id] ?? 0);
			$valid_winners = array(absint($tie['manager_a_id']), absint($tie['manager_b_id']));
			if ($chosen_winner && !in_array($chosen_winner, $valid_winners, true)) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('dlh_cup_invalid_winner', __('Choose a manager from the tie as the winner.', 'draft-league-hub'));
			}

			$winner_id = $chosen_winner;
			$higher_scorer = $score_a === $score_b ? 0 : ($score_a > $score_b ? absint($tie['manager_a_id']) : absint($tie['manager_b_id']));
			if ($winner_id && $higher_scorer && $winner_id !== $higher_scorer) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('dlh_cup_winner_score_mismatch', __('For an unequal score, the higher-scoring manager must advance.', 'draft-league-hub'));
			}
			if (!$winner_id && $score_a !== $score_b) {
				$winner_id = $higher_scorer;
			}
			if (!$winner_id && $score_a === $score_b) {
				$status = 'tied';
			} else {
				$status = 'complete';
			}

			$old_winner = absint($tie['winner_manager_id']);
			if ($old_winner && $old_winner !== $winner_id && $this->draft_cup_downstream_has_result($tie_id)) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('dlh_cup_downstream_locked', __('An earlier winner cannot be changed after the next tie has received scores.', 'draft-league-hub'));
			}

			$now = current_time('mysql', true);
			$saved = $wpdb->update(
				$ties_table,
				array(
					'score_a' => $score_a,
					'score_b' => $score_b,
					'winner_manager_id' => $winner_id ?: null,
					'status' => $status,
					'score_updated_at' => $now,
					'updated_at' => $now,
				),
				array('id' => $tie_id),
				array('%d', '%d', '%d', '%s', '%s', '%s'),
				array('%d')
			);
			if (false === $saved) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('dlh_cup_score_save_failed', __('A Draft Cup score could not be saved.', 'draft-league-hub'));
			}
			$updated++;
		}

		$this->propagate_draft_cup_winners($cup_id);
		$this->update_draft_cup_status($cup_id);
		$wpdb->query('COMMIT');
		return $updated;
	}


	private function draft_cup_downstream_has_result($tie_id) {
		global $wpdb;
		$table_name = $this->draft_cup_ties_table_name();
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name}
				WHERE (source_a_tie_id = %d OR source_b_tie_id = %d)
				AND (score_a IS NOT NULL OR score_b IS NOT NULL OR winner_manager_id IS NOT NULL)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint($tie_id),
				absint($tie_id)
			)
		);
		return absint($count) > 0;
	}


	private function propagate_draft_cup_winners($cup_id) {
		global $wpdb;

		$ties_table = $this->draft_cup_ties_table_name();
		$ties = $this->get_draft_cup_ties($cup_id);
		$tie_map = array();
		foreach ($ties as $tie) {
			$tie_map[absint($tie['id'])] = $tie;
		}

		foreach ($ties as $tie) {
			if (empty($tie['source_a_tie_id']) && empty($tie['source_b_tie_id'])) {
				continue;
			}
			$locked = null !== $tie['score_a'] || null !== $tie['score_b'] || !empty($tie['winner_manager_id']);
			if ($locked) {
				continue;
			}

			$data = array();
			$formats = array();
			foreach (array('a', 'b') as $slot) {
				$source_id = absint($tie['source_' . $slot . '_tie_id']);
				if (!$source_id) {
					continue;
				}
				$winner_id = absint($tie_map[$source_id]['winner_manager_id'] ?? 0);
				$current_id = absint($tie['manager_' . $slot . '_id']);
				if ($winner_id !== $current_id) {
					$data['manager_' . $slot . '_id'] = $winner_id ?: null;
					$formats[] = '%d';
				}
			}
			if ($data) {
				$data['updated_at'] = current_time('mysql', true);
				$formats[] = '%s';
				$wpdb->update($ties_table, $data, array('id' => absint($tie['id'])), $formats, array('%d'));
			}
		}
	}


	private function update_draft_cup_status($cup_id) {
		global $wpdb;

		$ties = $this->get_draft_cup_ties($cup_id);
		$final = null;
		$has_activity = false;
		foreach ($ties as $tie) {
			if (4 === absint($tie['round_number'])) {
				$final = $tie;
			}
			if (null !== $tie['score_a'] || null !== $tie['score_b'] || in_array($tie['status'], array('live', 'tied', 'complete'), true)) {
				$has_activity = true;
			}
		}

		$champion_id = absint($final['winner_manager_id'] ?? 0);
		$status = $champion_id ? 'complete' : ($has_activity ? 'live' : 'scheduled');
		$wpdb->update(
			$this->draft_cups_table_name(),
			array(
				'status' => $status,
				'champion_manager_id' => $champion_id ?: null,
				'updated_at' => current_time('mysql', true),
			),
			array('id' => absint($cup_id)),
			array('%s', '%d', '%s'),
			array('%d')
		);
	}


	private function draft_cup_admin_slot_name($tie, $slot) {
		$manager_id = absint($tie['manager_' . $slot . '_id'] ?? 0);
		if ($manager_id) {
			return $this->manager_name($manager_id);
		}
		$source_id = absint($tie['source_' . $slot . '_tie_id'] ?? 0);
		if ($source_id) {
			$source = $this->get_draft_cup_tie($source_id);
			if ($source) {
				return sprintf(__('Winner of %1$s %2$d', 'draft-league-hub'), $this->draft_cup_round_labels()[$source['round_number']], absint($source['match_number']));
			}
		}
		return __('To be decided', 'draft-league-hub');
	}


	public function maybe_refresh_current_draft_cup() {
		$current_season = $this->get_current_season();
		if (!$current_season) {
			return;
		}
		$cup = $this->get_draft_cup_for_season($current_season['id']);
		if ($cup && 'complete' !== $cup['status']) {
			$this->refresh_draft_cup_scores($cup['id']);
		}
	}


	public function shortcode_draft_cup() {
		$seasons = $this->get_seasons();
		$current_season = $this->get_current_season();
		$requested = isset($_GET['cup_season']) && is_string($_GET['cup_season']) ? sanitize_key(wp_unslash($_GET['cup_season'])) : '';
		$selected_season = $current_season;
		if ($requested) {
			foreach ($seasons as $season) {
				if ($requested === $season['slug']) {
					$selected_season = $season;
					break;
				}
			}
		}

		$cup = $selected_season ? $this->get_draft_cup_for_season($selected_season['id']) : null;
		$ties = $cup ? $this->get_draft_cup_ties($cup['id']) : array();
		$rounds = array(1 => array(), 2 => array(), 3 => array(), 4 => array());
		$tie_map = array();
		$last_updated = '';
		foreach ($ties as $tie) {
			$rounds[absint($tie['round_number'])][] = $tie;
			$tie_map[absint($tie['id'])] = $tie;
			if (!empty($tie['score_updated_at']) && $tie['score_updated_at'] > $last_updated) {
				$last_updated = $tie['score_updated_at'];
			}
		}
		$status_labels = $this->draft_cup_status_labels();
		$round_labels = $this->draft_cup_round_labels();
		$base_url = get_permalink();

		ob_start();
		?>
		<div class="dlh-wrap dlh-section dlh-draft-cup">
			<div class="dlh-section__head">
				<div><p class="dlh-kicker"><?php echo esc_html__('One bad gameweek and you are out.', 'draft-league-hub'); ?></p><h2><?php echo esc_html__('Draft Cup', 'draft-league-hub'); ?></h2><p><?php echo esc_html__('Twelve managers. Four gameweeks. One champion.', 'draft-league-hub'); ?></p></div>
				<?php if ($selected_season) : ?><span class="dlh-pill"><?php echo esc_html($selected_season['label']); ?></span><?php endif; ?>
			</div>

			<?php if ($seasons) : ?>
				<nav class="dlh-season-tabs" aria-label="<?php echo esc_attr__('Draft Cup seasons', 'draft-league-hub'); ?>">
				<?php foreach ($seasons as $season) : ?>
					<?php $is_active = absint($season['id']) === absint($selected_season['id'] ?? 0); ?>
					<a class="dlh-season-tab<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('cup_season', $season['slug'], $base_url)); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($season['label']); ?></a>
				<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<?php if (!$cup) : ?>
				<div class="dlh-empty"><strong><?php echo esc_html__('The draw has not been made yet.', 'draft-league-hub'); ?></strong><p><?php echo esc_html__('Once the starting gameweek is chosen and the teams are drawn, the full knockout bracket will appear here.', 'draft-league-hub'); ?></p></div>
			<?php else : ?>
				<section class="dlh-cup-hero">
					<div><span><?php echo esc_html($status_labels[$cup['status']] ?? ucfirst($cup['status'])); ?></span><h3><?php echo esc_html($cup['name']); ?></h3><p><?php echo esc_html(sprintf(__('Gameweeks %1$d–%2$d', 'draft-league-hub'), absint($cup['start_gameweek']), absint($cup['start_gameweek']) + 3)); ?></p></div>
					<?php if (!empty($cup['champion_manager_id'])) : ?><div class="dlh-cup-champion"><span><?php echo esc_html__('Champion', 'draft-league-hub'); ?></span><strong><?php echo esc_html($this->manager_name($cup['champion_manager_id'])); ?></strong><small><?php echo esc_html(get_post_meta($cup['champion_manager_id'], 'dlh_team_name', true)); ?></small></div><?php else : ?><div class="dlh-cup-trophy" aria-hidden="true">🏆</div><?php endif; ?>
				</section>

				<section class="dlh-cup-byes">
					<div><span><?php echo esc_html__('Opening-round byes', 'draft-league-hub'); ?></span><strong><?php echo esc_html__('Straight into the quarter-finals', 'draft-league-hub'); ?></strong></div>
					<div class="dlh-cup-byes__list"><?php foreach ($rounds[2] as $quarter_final) : ?><?php if (!empty($quarter_final['manager_a_id']) && empty($quarter_final['source_a_tie_id'])) : ?><span><?php echo esc_html($this->manager_name($quarter_final['manager_a_id'])); ?></span><?php endif; ?><?php endforeach; ?></div>
				</section>

				<div class="dlh-cup-bracket-wrap">
					<div class="dlh-cup-bracket">
					<?php foreach ($rounds as $round_number => $round_ties) : ?>
						<section class="dlh-cup-round dlh-cup-round--<?php echo esc_attr($round_number); ?>">
							<header><span><?php echo esc_html(sprintf(__('Gameweek %d', 'draft-league-hub'), absint($cup['start_gameweek']) + $round_number - 1)); ?></span><h3><?php echo esc_html($round_labels[$round_number]); ?></h3></header>
							<div class="dlh-cup-round__ties">
							<?php foreach ($round_ties as $tie) : ?>
								<?php echo $this->render_draft_cup_tie($tie, $tie_map); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>
					</div>
				</div>
				<p class="dlh-footnote"><?php echo esc_html($last_updated ? sprintf(__('Scores last updated %s.', 'draft-league-hub'), mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $last_updated . ' GMT')) : __('Scores will appear after the first cup gameweek begins.', 'draft-league-hub')); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}


	private function render_draft_cup_tie($tie, $tie_map) {
		$status_labels = $this->draft_cup_status_labels();
		$status = $tie['status'];
		ob_start();
		?>
		<article class="dlh-cup-tie dlh-cup-tie--<?php echo esc_attr($status); ?>">
			<div class="dlh-cup-tie__meta"><span><?php echo esc_html(sprintf(__('Tie %d', 'draft-league-hub'), absint($tie['match_number']))); ?></span><span><?php echo esc_html($status_labels[$status] ?? ucfirst($status)); ?></span></div>
			<?php foreach (array('a', 'b') as $slot) : ?>
				<?php
				$manager_id = absint($tie['manager_' . $slot . '_id']);
				$winner = $manager_id && $manager_id === absint($tie['winner_manager_id']);
				$source_id = absint($tie['source_' . $slot . '_tie_id']);
				$source = $source_id ? ($tie_map[$source_id] ?? null) : null;
				$placeholder = $source ? sprintf(__('Winner of %1$s %2$d', 'draft-league-hub'), $this->draft_cup_round_labels()[$source['round_number']], absint($source['match_number'])) : __('To be decided', 'draft-league-hub');
				?>
				<div class="dlh-cup-team<?php echo $winner ? ' is-winner' : ''; ?>">
					<div><?php if ($manager_id) : ?><strong><?php echo esc_html($this->manager_name($manager_id)); ?></strong><small><?php echo esc_html(get_post_meta($manager_id, 'dlh_team_name', true)); ?></small><?php else : ?><strong><?php echo esc_html($placeholder); ?></strong><small><?php echo esc_html__('Awaiting previous tie', 'draft-league-hub'); ?></small><?php endif; ?></div>
					<span><?php echo esc_html(null === $tie['score_' . $slot] ? '—' : $tie['score_' . $slot]); ?></span>
				</div>
			<?php endforeach; ?>
		</article>
		<?php
		return ob_get_clean();
	}
}
