<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Group_Picks {


	private function group_pick_events_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dlh_pick_events';
	}


	private function group_pick_entries_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dlh_pick_entries';
	}


	private function group_pick_tables_exist() {
		global $wpdb;

		$events_table = $this->group_pick_events_table_name();
		$entries_table = $this->group_pick_entries_table_name();
		$events_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events_table));
		$entries_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $entries_table));

		return $events_table === $events_found && $entries_table === $entries_found;
	}


	private function group_pick_results() {
		return array(
			'pending' => __('Pending', 'draft-league-hub'),
			'win' => __('Win', 'draft-league-hub'),
			'loss' => __('Loss', 'draft-league-hub'),
			'void' => __('Void', 'draft-league-hub'),
		);
	}


	public function register_group_picks_admin_page() {
		add_submenu_page(
			'edit.php?post_type=dlh_manager',
			__('Groupie Picks', 'draft-league-hub'),
			__('Groupie Picks', 'draft-league-hub'),
			'manage_options',
			'dlh-group-picks',
			array($this, 'render_group_picks_admin_page')
		);
	}


	public function render_group_picks_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['dlh_save_group_pick_event'])) {
			check_admin_referer('dlh_save_group_pick_event');
			$result = $this->save_group_pick_event_from_request();
			if (is_wp_error($result)) {
				$notice = $result->get_error_message();
			} else {
				$url = add_query_arg(
					array(
						'post_type' => 'dlh_manager',
						'page' => 'dlh-group-picks',
						'edit' => absint($result),
						'saved' => 1,
					),
					admin_url('edit.php')
				);
				wp_safe_redirect($url);
				exit;
			}
		}

		$current_season = $this->get_current_season();
		$seasons = $this->get_seasons();
		$managers = $this->get_managers();
		$edit_id = absint($_GET['edit'] ?? 0);
		$event = $edit_id ? $this->get_group_pick_event($edit_id) : null;
		$entries = $event ? $this->get_group_pick_entries_for_event($edit_id) : array();
		$entry_map = array();
		foreach ($entries as $entry) {
			$entry_map[absint($entry['manager_id'])] = $entry;
		}

		$selected_season_id = absint($event['season_id'] ?? ($current_season['id'] ?? 0));
		$title = (string) ($event['title'] ?? '');
		$event_date = (string) ($event['event_date'] ?? current_time('Y-m-d'));
		$gameweek = absint($event['gameweek'] ?? 0);
		$notes = (string) ($event['notes'] ?? '');
		$recent_events = $this->get_group_pick_events(null, 50);
		?>
		<div class="wrap dlh-picks-admin">
			<h1><?php echo esc_html__('Groupie Picks', 'draft-league-hub'); ?></h1>
			<p><?php echo esc_html__('Record one Groupie Picks round at a time. Empty manager rows are ignored; clearing an existing pick removes that entry.', 'draft-league-hub'); ?></p>

			<?php if (!empty($_GET['saved'])) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__('The picks round was saved.', 'draft-league-hub'); ?></p></div>
			<?php endif; ?>
			<?php if (!empty($notice)) : ?>
				<div class="notice notice-error"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
			<?php if (!$current_season) : ?>
				<div class="notice notice-error"><p><?php echo esc_html__('Set up the current season in Settings > Draft League Hub before recording picks.', 'draft-league-hub'); ?></p></div>
			<?php elseif (!$managers) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html__('Add or sync managers before recording picks.', 'draft-league-hub'); ?></p></div>
			<?php else : ?>
				<div class="dlh-picks-admin__layout">
					<form method="post" action="" class="dlh-picks-admin__form">
						<?php wp_nonce_field('dlh_save_group_pick_event'); ?>
						<input type="hidden" name="event_id" value="<?php echo esc_attr($edit_id); ?>">
						<h2><?php echo esc_html($event ? __('Edit picks round', 'draft-league-hub') : __('Add picks round', 'draft-league-hub')); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="dlh_pick_title"><?php echo esc_html__('Round title', 'draft-league-hub'); ?></label></th>
								<td><input class="regular-text" type="text" id="dlh_pick_title" name="title" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo esc_attr__('Champions League – Tuesday', 'draft-league-hub'); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="dlh_pick_season"><?php echo esc_html__('Season', 'draft-league-hub'); ?></label></th>
								<td><select id="dlh_pick_season" name="season_id">
									<?php foreach ($seasons as $season) : ?>
										<option value="<?php echo esc_attr($season['id']); ?>" <?php selected($selected_season_id, absint($season['id'])); ?>><?php echo esc_html($season['label']); ?><?php echo 'current' === $season['status'] ? ' · ' . esc_html__('Current', 'draft-league-hub') : ''; ?></option>
									<?php endforeach; ?>
								</select></td>
							</tr>
							<tr>
								<th scope="row"><label for="dlh_pick_date"><?php echo esc_html__('Date', 'draft-league-hub'); ?></label></th>
								<td><input type="date" id="dlh_pick_date" name="event_date" value="<?php echo esc_attr($event_date); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="dlh_pick_gameweek"><?php echo esc_html__('Gameweek', 'draft-league-hub'); ?></label></th>
								<td><input type="number" id="dlh_pick_gameweek" name="gameweek" value="<?php echo esc_attr($gameweek ?: ''); ?>" min="1" max="38" placeholder="<?php echo esc_attr__('Optional', 'draft-league-hub'); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="dlh_pick_notes"><?php echo esc_html__('Notes', 'draft-league-hub'); ?></label></th>
								<td><textarea class="large-text" id="dlh_pick_notes" name="notes" rows="2"><?php echo esc_textarea($notes); ?></textarea></td>
							</tr>
						</table>

						<h2><?php echo esc_html__('Manager picks', 'draft-league-hub'); ?></h2>
						<div class="dlh-picks-admin__table-wrap">
							<table class="widefat striped dlh-picks-admin__table">
								<thead><tr><th><?php echo esc_html__('Manager', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Pick', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Result', 'draft-league-hub'); ?></th></tr></thead>
								<tbody>
								<?php foreach ($managers as $manager) : ?>
									<?php
									$manager_id = absint($manager->ID);
									$manager_entry = $entry_map[$manager_id] ?? array();
									$team_name = get_post_meta($manager_id, 'dlh_team_name', true);
									$current_result = $manager_entry['result'] ?? 'pending';
									?>
									<tr>
										<th scope="row"><strong><?php echo esc_html(get_the_title($manager)); ?></strong><?php if ($team_name) : ?><small><?php echo esc_html($team_name); ?></small><?php endif; ?></th>
										<td><input class="large-text" type="text" name="pick[<?php echo esc_attr($manager_id); ?>]" value="<?php echo esc_attr($manager_entry['pick_text'] ?? ''); ?>" placeholder="<?php echo esc_attr__('Enter their pick', 'draft-league-hub'); ?>"></td>
										<td><select name="result[<?php echo esc_attr($manager_id); ?>]">
											<?php foreach ($this->group_pick_results() as $result_key => $result_label) : ?>
												<option value="<?php echo esc_attr($result_key); ?>" <?php selected($current_result, $result_key); ?>><?php echo esc_html($result_label); ?></option>
											<?php endforeach; ?>
										</select></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php submit_button($event ? __('Update picks round', 'draft-league-hub') : __('Save picks round', 'draft-league-hub'), 'primary', 'dlh_save_group_pick_event'); ?>
						<?php if ($event) : ?><a class="button" href="<?php echo esc_url(add_query_arg(array('post_type' => 'dlh_manager', 'page' => 'dlh-group-picks'), admin_url('edit.php'))); ?>"><?php echo esc_html__('Start a new round', 'draft-league-hub'); ?></a><?php endif; ?>
					</form>
				</div>
			<?php endif; ?>

			<h2><?php echo esc_html__('Recent rounds', 'draft-league-hub'); ?></h2>
			<?php if ($recent_events) : ?>
				<table class="widefat striped dlh-picks-admin__recent">
					<thead><tr><th><?php echo esc_html__('Date', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Round', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Season', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Picks', 'draft-league-hub'); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ($recent_events as $recent_event) : ?>
						<tr>
							<td><?php echo esc_html(mysql2date(get_option('date_format'), $recent_event['event_date'])); ?></td>
							<td><strong><?php echo esc_html($recent_event['title']); ?></strong><?php if (!empty($recent_event['gameweek'])) : ?> <span class="description"><?php echo esc_html(sprintf(__('GW%d', 'draft-league-hub'), absint($recent_event['gameweek']))); ?></span><?php endif; ?></td>
							<td><?php echo esc_html($recent_event['season_label']); ?></td>
							<td><?php echo esc_html(absint($recent_event['entry_count'])); ?></td>
							<td><a href="<?php echo esc_url(add_query_arg(array('post_type' => 'dlh_manager', 'page' => 'dlh-group-picks', 'edit' => absint($recent_event['id'])), admin_url('edit.php'))); ?>"><?php echo esc_html__('Edit', 'draft-league-hub'); ?></a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php echo esc_html__('No Groupie Picks rounds have been recorded yet.', 'draft-league-hub'); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}


	private function save_group_pick_event_from_request() {
		global $wpdb;

		if (!current_user_can('manage_options')) {
			return new WP_Error('dlh_pick_permission_denied', __('You do not have permission to record Groupie Picks.', 'draft-league-hub'));
		}

		$event_id = absint($_POST['event_id'] ?? 0);
		$season_id = absint($_POST['season_id'] ?? 0);
		$title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
		$event_date = sanitize_text_field(wp_unslash($_POST['event_date'] ?? ''));
		$gameweek = absint($_POST['gameweek'] ?? 0);
		$notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

		$date_parts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $event_date, $date_matches) ? array_map('absint', array_slice($date_matches, 1)) : array();
		$valid_date = 3 === count($date_parts) && checkdate($date_parts[1], $date_parts[2], $date_parts[0]);
		if (!$season_id || !$title || !$valid_date) {
			return new WP_Error('dlh_pick_required_fields', __('Add a season, round title, and valid date.', 'draft-league-hub'));
		}
		if ($gameweek > 38) {
			return new WP_Error('dlh_pick_invalid_gameweek', __('Gameweek must be between 1 and 38.', 'draft-league-hub'));
		}

		$season_ids = array_map('absint', wp_list_pluck($this->get_seasons(), 'id'));
		if (!in_array($season_id, $season_ids, true)) {
			return new WP_Error('dlh_pick_invalid_season', __('Choose a valid season.', 'draft-league-hub'));
		}
		if ($event_id && !$this->get_group_pick_event($event_id)) {
			return new WP_Error('dlh_pick_event_missing', __('That picks round could not be found.', 'draft-league-hub'));
		}

		$events_table = $this->group_pick_events_table_name();
		$entries_table = $this->group_pick_entries_table_name();
		$now = current_time('mysql', true);
		$wpdb->query('START TRANSACTION');

		$event_data = array(
			'season_id' => $season_id,
			'title' => $title,
			'event_date' => $event_date,
			'gameweek' => $gameweek ?: null,
			'notes' => $notes,
			'updated_at' => $now,
		);
		$event_formats = array('%d', '%s', '%s', '%d', '%s', '%s');

		if ($event_id) {
			$saved = $wpdb->update($events_table, $event_data, array('id' => $event_id), $event_formats, array('%d'));
		} else {
			$event_data['created_at'] = $now;
			$event_formats[] = '%s';
			$saved = $wpdb->insert($events_table, $event_data, $event_formats);
			$event_id = absint($wpdb->insert_id);
		}

		if (false === $saved || !$event_id) {
			$wpdb->query('ROLLBACK');
			return new WP_Error('dlh_pick_event_save_failed', __('The picks round could not be saved.', 'draft-league-hub'));
		}

		$raw_picks = isset($_POST['pick']) && is_array($_POST['pick']) ? wp_unslash($_POST['pick']) : array();
		$raw_results = isset($_POST['result']) && is_array($_POST['result']) ? wp_unslash($_POST['result']) : array();
		$valid_results = array_keys($this->group_pick_results());

		foreach ($this->get_managers() as $manager) {
			$manager_id = absint($manager->ID);
			$pick_text = sanitize_text_field($raw_picks[$manager_id] ?? '');
			$result = sanitize_key($raw_results[$manager_id] ?? 'pending');
			$result = in_array($result, $valid_results, true) ? $result : 'pending';

			if ('' === $pick_text) {
				$entry_saved = $wpdb->delete($entries_table, array('event_id' => $event_id, 'manager_id' => $manager_id), array('%d', '%d'));
				if (false === $entry_saved) {
					$wpdb->query('ROLLBACK');
					return new WP_Error('dlh_pick_entry_save_failed', __('One or more manager picks could not be saved.', 'draft-league-hub'));
				}
				continue;
			}

			$entry_saved = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$entries_table} (event_id, manager_id, pick_text, result, created_at, updated_at)
					VALUES (%d, %d, %s, %s, %s, %s)
					ON DUPLICATE KEY UPDATE pick_text = VALUES(pick_text), result = VALUES(result), updated_at = VALUES(updated_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$event_id,
					$manager_id,
					$pick_text,
					$result,
					$now,
					$now
				)
			);
			if (false === $entry_saved) {
				$wpdb->query('ROLLBACK');
				return new WP_Error('dlh_pick_entry_save_failed', __('One or more manager picks could not be saved.', 'draft-league-hub'));
			}
		}

		$wpdb->query('COMMIT');
		return $event_id;
	}


	private function get_group_pick_event($event_id) {
		global $wpdb;
		$table_name = $this->group_pick_events_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", absint($event_id)), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}


	private function get_group_pick_entries_for_event($event_id) {
		global $wpdb;
		$table_name = $this->group_pick_entries_table_name();
		$entries = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table_name} WHERE event_id = %d ORDER BY id ASC", absint($event_id)), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		return is_array($entries) ? $entries : array();
	}


	private function get_group_pick_events($season_id = null, $limit = 20) {
		global $wpdb;
		$events_table = $this->group_pick_events_table_name();
		$entries_table = $this->group_pick_entries_table_name();
		$seasons_table = $this->seasons_table_name();
		$where = '';
		if (null !== $season_id) {
			$where = $wpdb->prepare('WHERE events.season_id = %d', absint($season_id));
		}
		$sql = "SELECT events.*, seasons.label AS season_label,
			(SELECT COUNT(*) FROM {$entries_table} counted_entries WHERE counted_entries.event_id = events.id) AS entry_count
			FROM {$events_table} events
			INNER JOIN {$seasons_table} seasons ON seasons.id = events.season_id
			{$where}
			ORDER BY events.event_date DESC, events.id DESC
			LIMIT %d";
		$events = $wpdb->get_results($wpdb->prepare($sql, max(1, absint($limit))), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array($events) ? $events : array();
	}


	private function get_group_pick_leaderboard($season_id = null) {
		global $wpdb;
		$events_table = $this->group_pick_events_table_name();
		$entries_table = $this->group_pick_entries_table_name();
		$where = '';
		if (null !== $season_id) {
			$where = $wpdb->prepare('WHERE events.season_id = %d', absint($season_id));
		}

		$aggregate = $wpdb->get_results(
			"SELECT entries.manager_id,
				SUM(CASE WHEN entries.result = 'win' THEN 1 ELSE 0 END) AS wins,
				SUM(CASE WHEN entries.result = 'loss' THEN 1 ELSE 0 END) AS losses,
				SUM(CASE WHEN entries.result = 'void' THEN 1 ELSE 0 END) AS voids,
				SUM(CASE WHEN entries.result = 'pending' THEN 1 ELSE 0 END) AS pending
			FROM {$entries_table} entries
			INNER JOIN {$events_table} events ON events.id = entries.event_id
			{$where}
			GROUP BY entries.manager_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$aggregate_map = array();
		foreach ((array) $aggregate as $row) {
			$aggregate_map[absint($row['manager_id'])] = $row;
		}

		$leaderboard = array();
		foreach ($this->get_managers() as $manager) {
			$manager_id = absint($manager->ID);
			$stats = $aggregate_map[$manager_id] ?? array();
			$wins = absint($stats['wins'] ?? 0);
			$losses = absint($stats['losses'] ?? 0);
			$voids = absint($stats['voids'] ?? 0);
			$pending = absint($stats['pending'] ?? 0);
			$graded = $wins + $losses;
			$leaderboard[] = array(
				'manager_id' => $manager_id,
				'name' => get_the_title($manager),
				'team_name' => get_post_meta($manager_id, 'dlh_team_name', true),
				'wins' => $wins,
				'losses' => $losses,
				'voids' => $voids,
				'pending' => $pending,
				'graded' => $graded,
				'total' => $graded + $voids + $pending,
				'win_percentage' => $graded ? ($wins / $graded) * 100 : null,
			);
		}

		usort(
			$leaderboard,
			function ($left, $right) {
				$left_percentage = null === $left['win_percentage'] ? -1 : $left['win_percentage'];
				$right_percentage = null === $right['win_percentage'] ? -1 : $right['win_percentage'];
				if (abs($left_percentage - $right_percentage) > 0.0001) {
					return $left_percentage < $right_percentage ? 1 : -1;
				}
				if ($left['graded'] !== $right['graded']) {
					return $right['graded'] <=> $left['graded'];
				}
				if ($left['wins'] !== $right['wins']) {
					return $right['wins'] <=> $left['wins'];
				}
				return strcasecmp($left['name'], $right['name']);
			}
		);

		return $leaderboard;
	}


	public function shortcode_group_picks() {
		$seasons = $this->get_seasons();
		$current_season = $this->get_current_season();
		$requested = sanitize_key(wp_unslash($_GET['pick_season'] ?? ''));
		$selected_season = $current_season;
		$all_time = 'all' === $requested;

		if ($requested && !$all_time) {
			foreach ($seasons as $season) {
				if ($requested === $season['slug']) {
					$selected_season = $season;
					break;
				}
			}
		}

		$season_id = $all_time ? null : absint($selected_season['id'] ?? 0);
		$leaderboard = $this->get_group_pick_leaderboard($season_id ?: null);
		$events = $this->get_group_pick_events($season_id ?: null, 20);
		$summary = $this->get_group_pick_summary($leaderboard);
		$base_url = get_permalink();

		ob_start();
		?>
		<div class="dlh-wrap dlh-section dlh-group-picks">
			<div class="dlh-section__head">
				<div>
					<p class="dlh-kicker"><?php echo esc_html__('The group knows best. Allegedly.', 'draft-league-hub'); ?></p>
					<h2><?php echo esc_html__('Groupie Picks', 'draft-league-hub'); ?></h2>
					<p><?php echo esc_html__('Every call, every result, and the win percentage table that settles who actually knows ball.', 'draft-league-hub'); ?></p>
				</div>
				<span class="dlh-pill"><?php echo esc_html($all_time ? __('All time', 'draft-league-hub') : ($selected_season['label'] ?? __('Current season', 'draft-league-hub'))); ?></span>
			</div>

			<nav class="dlh-season-tabs" aria-label="<?php echo esc_attr__('Groupie Picks seasons', 'draft-league-hub'); ?>">
				<?php foreach ($seasons as $season) : ?>
					<?php $is_active = !$all_time && absint($selected_season['id'] ?? 0) === absint($season['id']); ?>
					<a class="dlh-season-tab<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('pick_season', $season['slug'], $base_url)); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($season['label']); ?></a>
				<?php endforeach; ?>
				<a class="dlh-season-tab<?php echo $all_time ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('pick_season', 'all', $base_url)); ?>"<?php echo $all_time ? ' aria-current="page"' : ''; ?>><?php echo esc_html__('All time', 'draft-league-hub'); ?></a>
			</nav>

			<div class="dlh-pick-summary">
				<?php echo $this->render_group_pick_summary_card(__('Best picker', 'draft-league-hub'), $summary['best'], 'best'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->render_group_pick_summary_card(__('Most wins', 'draft-league-hub'), $summary['most_wins'], 'wins'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->render_group_pick_summary_card(__('Bottom of the pile', 'draft-league-hub'), $summary['worst'], 'worst'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="dlh-pick-summary__card"><span><?php echo esc_html__('Picks recorded', 'draft-league-hub'); ?></span><strong><?php echo esc_html($summary['total']); ?></strong><small><?php echo esc_html__('including pending and void', 'draft-league-hub'); ?></small></div>
			</div>

			<section class="dlh-panel">
				<div class="dlh-section__head">
					<div><h3><?php echo esc_html__('Win percentage leaderboard', 'draft-league-hub'); ?></h3><p><?php echo esc_html__('Win % is wins divided by graded picks. Void and pending picks do not count.', 'draft-league-hub'); ?></p></div>
				</div>
				<div class="dlh-table-wrap">
					<table class="dlh-table dlh-picks-table">
						<thead><tr><th><?php echo esc_html__('#', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Manager', 'draft-league-hub'); ?></th><th><?php echo esc_html__('W', 'draft-league-hub'); ?></th><th><?php echo esc_html__('L', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Void', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Pending', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Graded', 'draft-league-hub'); ?></th><th><?php echo esc_html__('Win %', 'draft-league-hub'); ?></th></tr></thead>
						<tbody>
						<?php foreach ($leaderboard as $index => $row) : ?>
							<tr>
								<td><strong><?php echo esc_html($index + 1); ?></strong></td>
								<td><strong><?php echo esc_html($row['name']); ?></strong><?php if ($row['team_name']) : ?><small><?php echo esc_html($row['team_name']); ?></small><?php endif; ?></td>
								<td><?php echo esc_html($row['wins']); ?></td><td><?php echo esc_html($row['losses']); ?></td><td><?php echo esc_html($row['voids']); ?></td><td><?php echo esc_html($row['pending']); ?></td><td><?php echo esc_html($row['graded']); ?></td>
								<td><strong class="dlh-picks-table__percentage"><?php echo esc_html(null === $row['win_percentage'] ? '—' : number_format_i18n($row['win_percentage'], 1) . '%'); ?></strong><?php if ($row['graded'] > 0 && $row['graded'] < 3) : ?><span class="dlh-pick-provisional"><?php echo esc_html__('Provisional', 'draft-league-hub'); ?></span><?php endif; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section>
				<div class="dlh-section__head"><div><h3><?php echo esc_html__('Recent picks', 'draft-league-hub'); ?></h3><p><?php echo esc_html__('The latest rounds and how every call finished.', 'draft-league-hub'); ?></p></div></div>
				<div class="dlh-pick-history">
				<?php if ($events) : ?>
					<?php foreach ($events as $event) : ?>
						<article class="dlh-panel dlh-pick-event">
							<header class="dlh-pick-event__head">
								<div><span><?php echo esc_html(mysql2date(get_option('date_format'), $event['event_date'])); ?><?php if (!empty($event['gameweek'])) : ?> · <?php echo esc_html(sprintf(__('GW%d', 'draft-league-hub'), absint($event['gameweek']))); ?><?php endif; ?></span><h4><?php echo esc_html($event['title']); ?></h4></div>
								<span class="dlh-pill"><?php echo esc_html(sprintf(_n('%d pick', '%d picks', absint($event['entry_count']), 'draft-league-hub'), absint($event['entry_count']))); ?></span>
							</header>
							<?php if (!empty($event['notes'])) : ?><p class="dlh-pick-event__notes"><?php echo esc_html($event['notes']); ?></p><?php endif; ?>
							<div class="dlh-pick-event__entries">
							<?php foreach ($this->get_group_pick_entries_for_event($event['id']) as $entry) : ?>
								<div class="dlh-pick-entry"><div><strong><?php echo esc_html($this->manager_name($entry['manager_id'])); ?></strong><span><?php echo esc_html($entry['pick_text']); ?></span></div><span class="dlh-pick-result dlh-pick-result--<?php echo esc_attr($entry['result']); ?>"><?php echo esc_html($this->group_pick_results()[$entry['result']] ?? ucfirst($entry['result'])); ?></span></div>
							<?php endforeach; ?>
							</div>
						</article>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="dlh-empty"><?php echo esc_html__('No picks have been recorded for this view yet. The leaderboard is ready when the first round is entered.', 'draft-league-hub'); ?></div>
				<?php endif; ?>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}


	private function get_group_pick_summary($leaderboard) {
		$graded = array_values(array_filter($leaderboard, function ($row) { return $row['graded'] > 0; }));
		$qualified = array_values(array_filter($graded, function ($row) { return $row['graded'] >= 3; }));
		$pool = $qualified ? $qualified : $graded;
		$best = $pool ? $pool[0] : null;
		$worst = $pool ? $pool[count($pool) - 1] : null;
		$by_wins = $leaderboard;
		usort($by_wins, function ($left, $right) { return $right['wins'] <=> $left['wins'] ?: $right['graded'] <=> $left['graded']; });
		$most_wins = $by_wins && $by_wins[0]['wins'] ? $by_wins[0] : null;
		$total = array_sum(wp_list_pluck($leaderboard, 'total'));
		return compact('best', 'worst', 'most_wins', 'total');
	}


	private function render_group_pick_summary_card($label, $row, $mode) {
		if (!$row) {
			return '<div class="dlh-pick-summary__card"><span>' . esc_html($label) . '</span><strong>—</strong><small>' . esc_html__('Waiting for graded picks', 'draft-league-hub') . '</small></div>';
		}

		if ('wins' === $mode) {
			$value = sprintf(_n('%d win', '%d wins', $row['wins'], 'draft-league-hub'), $row['wins']);
		} else {
			$value = number_format_i18n($row['win_percentage'], 1) . '%';
		}

		return '<div class="dlh-pick-summary__card"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong><small>' . esc_html($row['name']) . '</small></div>';
	}
}
