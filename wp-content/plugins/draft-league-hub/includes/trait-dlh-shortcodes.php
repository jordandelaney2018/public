<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Shortcodes {


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
							<?php if (in_array($key, array('votes', 'sidebets', 'hall_of_fame', 'calendar', 'stats', 'group_picks', 'draft_cup'), true)) : ?>
								<a class="dlh-button" href="<?php echo esc_url($page['url']); ?>"><?php echo esc_html($page['label']); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="dlh-hero__panel">
					<span><?php echo esc_html($options['season_label']); ?></span>
					<strong><?php echo esc_html($options['league_name']); ?></strong>
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
		$questions = get_post_meta($vote_id, 'dlh_questions', true);
		$questions = is_array($questions) ? $questions : array();
		$votes = get_post_meta($vote_id, 'dlh_votes', true);
		$votes = is_array($votes) ? $votes : array();
		$vote_key = $this->current_vote_key(false);
		$user_vote = $this->get_current_vote_from_votes($votes, $vote_key);

		$show_results = $this->is_vote_closed($vote_id) || current_user_can('edit_posts');

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
			<?php if (!empty($user_vote['submitted'])) : ?>
				<div class="dlh-notice">
					<?php echo esc_html(sprintf(__('Your vote was last saved on %s.', 'draft-league-hub'), mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $user_vote['submitted']))); ?>
				</div>
			<?php endif; ?>

			<?php if ($this->is_vote_closed($vote_id)) : ?>
				<div class="dlh-empty"><?php echo esc_html__('Voting is closed for these awards.', 'draft-league-hub'); ?></div>
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
						?>
						<div class="dlh-fieldset">
							<label for="answer-<?php echo esc_attr($key); ?>"><?php echo esc_html($question['label']); ?></label>
							<?php if ('manager' === $type) : ?>
								<?php echo $this->manager_select('answer[' . esc_attr($key) . ']', absint($current_answer), __('Choose manager', 'draft-league-hub'), 'answer-' . esc_attr($key)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php else : ?>
								<input id="answer-<?php echo esc_attr($key); ?>" type="text" name="answer[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($current_answer); ?>" placeholder="<?php echo esc_attr__('Nomination', 'draft-league-hub'); ?>">
							<?php endif; ?>
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
					<textarea id="stipulation" name="stipulation" rows="3" required placeholder="<?php echo esc_attr__('Example: Neil to bum Joe to death this weekend.', 'draft-league-hub'); ?>"></textarea>
				</div>
				<div class="dlh-fieldset">
					<label for="stake"><?php echo esc_html__('Stake', 'draft-league-hub'); ?></label>
					<input id="stake" name="stake" type="text" required placeholder="<?php echo esc_attr__('Pint, £10, death wing, etc.', 'draft-league-hub'); ?>">
				</div>
				<?php if (current_user_can('edit_posts')) : ?>
					<button class="dlh-button" type="submit"><?php echo esc_html__('Add sidebet', 'draft-league-hub'); ?></button>
				<?php else : ?>
					<button class="dlh-button" type="submit"><?php echo esc_html__('Submit sidebet for approval', 'draft-league-hub'); ?></button>
				<?php endif; ?>
			</form>

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


	public function shortcode_hall_of_fame($atts = array()) {
		$atts = shortcode_atts(array('count' => 24), $atts, 'dlh_hall_of_fame');
		$requested_tab = isset($_GET['hof_tab']) && is_string($_GET['hof_tab']) ? sanitize_key(wp_unslash($_GET['hof_tab'])) : '';
		$active_tab = 'past-winners' === $requested_tab ? 'past-winners' : 'gallery';
		$is_winners = 'past-winners' === $active_tab;
		$query = new WP_Query(
			$is_winners ? array(
				'post_type' => 'dlh_past_winner',
				'post_status' => 'publish',
				'posts_per_page' => 100,
				'meta_key' => 'dlh_winner_sort_year',
				'orderby' => array('meta_value_num' => 'DESC', 'menu_order' => 'ASC', 'date' => 'DESC'),
			) : array(
				'post_type' => 'dlh_hof_entry',
				'post_status' => 'publish',
				'posts_per_page' => max(1, absint($atts['count'])),
				'orderby' => 'date',
				'order' => 'DESC',
			)
		);
		$base_url = get_permalink() ?: home_url('/');

		ob_start();
		?>
		<div class="dlh-wrap dlh-section">
			<div class="dlh-section__head">
				<div>
					<h2><?php echo esc_html__('Hall of Fame', 'draft-league-hub'); ?></h2>
					<p><?php echo esc_html($is_winners ? __('The champions, preserved for posterity.', 'draft-league-hub') : __('The worst things ever maybe', 'draft-league-hub')); ?></p>
				</div>
				<span class="dlh-pill"><?php echo esc_html($is_winners ? sprintf(_n('%d winner', '%d winners', $query->found_posts, 'draft-league-hub'), $query->found_posts) : sprintf(_n('%d entry', '%d entries', $query->found_posts, 'draft-league-hub'), $query->found_posts)); ?></span>
			</div>

			<nav class="dlh-hof-tabs" aria-label="<?php echo esc_attr__('Hall of Fame sections', 'draft-league-hub'); ?>">
				<a class="dlh-hof-tab<?php echo $is_winners ? '' : ' is-active'; ?>" href="<?php echo esc_url(remove_query_arg('hof_tab', $base_url)); ?>"<?php echo $is_winners ? '' : ' aria-current="page"'; ?>><?php echo esc_html__('Gallery', 'draft-league-hub'); ?></a>
				<a class="dlh-hof-tab<?php echo $is_winners ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('hof_tab', 'past-winners', $base_url)); ?>"<?php echo $is_winners ? ' aria-current="page"' : ''; ?>><?php echo esc_html__('Past Winners', 'draft-league-hub'); ?></a>
			</nav>

			<div class="<?php echo esc_attr($is_winners ? 'dlh-winner-grid' : 'dlh-hof-grid'); ?>">
				<?php if ($query->have_posts()) : ?>
					<?php while ($query->have_posts()) : ?>
						<?php $query->the_post(); ?>
						<?php echo $is_winners ? $this->render_past_winner_card(get_the_ID()) : $this->render_hall_of_fame_card(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<div class="dlh-empty"><?php echo esc_html($is_winners ? __('No past winners have been added yet. Add the first champion under Hall of Fame > Past Winners.', 'draft-league-hub') : __('No Hall of Fame entries yet. The evidence locker is empty.', 'draft-league-hub')); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}


	public function shortcode_calendar() {
		$query = new WP_Query(
			array(
				'post_type' => 'dlh_calendar_event',
				'post_status' => 'publish',
				'posts_per_page' => 30,
				'meta_key' => 'dlh_event_date',
				'meta_query' => array(
					array(
						'key' => 'dlh_event_date',
						'value' => current_time('Y-m-d'),
						'compare' => '>=',
						'type' => 'DATE',
					),
				),
				'orderby' => 'meta_value',
				'order' => 'ASC',
			)
		);

		ob_start();
		?>
		<div class="dlh-wrap dlh-section">
			<div class="dlh-section__head">
				<div>
					<h2><?php echo esc_html__('Draft Calendar', 'draft-league-hub'); ?></h2>
					<p><?php echo esc_html__('Upcoming dates, deadlines, and league admin bits.', 'draft-league-hub'); ?></p>
				</div>
				<span class="dlh-pill"><?php echo esc_html(sprintf(_n('%d date', '%d dates', $query->post_count, 'draft-league-hub'), $query->post_count)); ?></span>
			</div>

			<div class="dlh-calendar-list">
				<?php if ($query->have_posts()) : ?>
					<?php while ($query->have_posts()) : ?>
						<?php $query->the_post(); ?>
						<?php echo $this->render_calendar_event(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<div class="dlh-empty"><?php echo esc_html__('No upcoming draft dates yet. Add one under Draft Dates in the dashboard.', 'draft-league-hub'); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}


	public function shortcode_stats() {
		$seasons = $this->get_seasons();
		$selected_season = $this->selected_data_hub_season($seasons);
		$payload = array();
		$error_message = '';
		$using_saved_copy = false;
		$is_archived = $selected_season && 'archived' === $selected_season['status'];

		if ($selected_season) {
			if ($is_archived) {
				$payload = $this->get_season_snapshot($selected_season['id']);
				if (!$payload) {
					$error_message = __('No saved data is available for this archived season yet.', 'draft-league-hub');
				}
			} elseif (empty($selected_season['league_id'])) {
				$error_message = __('Add the current season FPL Draft league ID in Settings > Draft League Hub to enable live data.', 'draft-league-hub');
			} else {
				$league_id = $selected_season['league_id'];
				$details = $this->api_get('/api/league/' . rawurlencode($league_id) . '/details');
				if (is_wp_error($details)) {
					$payload = $this->get_season_snapshot($selected_season['id']);
					if ($payload) {
						$using_saved_copy = true;
					} else {
						$error_message = $details->get_error_message();
					}
				} else {
					$transactions = $this->api_get('/api/draft/league/' . rawurlencode($league_id) . '/transactions');
					$trades = $this->api_get('/api/draft/league/' . rawurlencode($league_id) . '/trades');
					$bootstrap = $this->api_get('/api/bootstrap-static');
					$draft = $this->api_get('/api/draft/' . rawurlencode($league_id) . '/choices');
					$warnings = array();
					if (is_wp_error($transactions)) {
						$warnings[] = $transactions->get_error_message();
					}
					if (is_wp_error($trades)) {
						$warnings[] = $trades->get_error_message();
					}
					if (is_wp_error($bootstrap)) {
						$warnings[] = $bootstrap->get_error_message();
					}
					if (is_wp_error($draft)) {
						$warnings[] = $draft->get_error_message();
					}
					$saved_payload = $warnings ? $this->get_season_snapshot($selected_season['id']) : array();

					$payload = array(
						'details' => $details,
						'transactions' => is_wp_error($transactions) ? ($saved_payload['transactions'] ?? array()) : $transactions,
						'trades' => is_wp_error($trades) ? ($saved_payload['trades'] ?? array()) : $trades,
						'bootstrap' => is_wp_error($bootstrap) ? ($saved_payload['bootstrap'] ?? array()) : $bootstrap,
						'draft' => is_wp_error($draft) ? ($saved_payload['draft'] ?? array()) : $draft,
						'warnings' => $warnings,
					);
					$this->record_current_season_snapshot(
						$payload['details'],
						$payload['transactions'],
						$payload['trades'],
						$payload['bootstrap'],
						$payload['draft'],
						$payload['warnings']
					);
				}
			}
		} else {
			$error_message = __('No season has been configured yet.', 'draft-league-hub');
		}

		ob_start();
		?>
		<div class="dlh-wrap dlh-section">
			<div class="dlh-section__head">
				<div>
					<h2><?php echo esc_html__('Data Hub', 'draft-league-hub'); ?></h2>
					<p><?php echo esc_html__('Standings, season records, transfers, trades, and the original draft.', 'draft-league-hub'); ?></p>
				</div>
				<?php if ($selected_season) : ?>
					<span class="dlh-pill"><?php echo esc_html($is_archived ? __('Season archive', 'draft-league-hub') : __('Current season', 'draft-league-hub')); ?></span>
				<?php endif; ?>
			</div>

			<?php echo $this->render_data_hub_season_tabs($seasons, $selected_season); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php if ($using_saved_copy) : ?>
				<div class="dlh-notice"><?php echo esc_html__('Live FPL Draft data is temporarily unavailable, so this is the latest saved copy.', 'draft-league-hub'); ?></div>
			<?php elseif ($is_archived && !empty($selected_season['snapshot_captured_at'])) : ?>
				<div class="dlh-notice"><?php echo esc_html(sprintf(__('Archived data captured on %s.', 'draft-league-hub'), get_date_from_gmt($selected_season['snapshot_captured_at'], get_option('date_format') . ' ' . get_option('time_format')))); ?></div>
			<?php endif; ?>

			<?php if ($error_message) : ?>
				<div class="dlh-empty"><?php echo esc_html($error_message); ?></div>
			<?php elseif ($payload) : ?>
				<?php echo $this->render_draft_recap($payload['details'] ?? array(), $payload['draft'] ?? array(), $payload['bootstrap'] ?? array(), $selected_season); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<section class="dlh-data-block" aria-labelledby="dlh-season-stats-title">
					<div class="dlh-section__head">
						<div>
							<p class="dlh-kicker"><?php echo esc_html($selected_season['label']); ?></p>
							<h3 id="dlh-season-stats-title"><?php echo esc_html__('League standings & stats', 'draft-league-hub'); ?></h3>
						</div>
					</div>
					<?php
					echo $this->render_standings(
						$payload['details'] ?? array(),
						$payload['transactions'] ?? array(),
						$payload['trades'] ?? array(),
						$payload['bootstrap'] ?? array(),
						!$is_archived && !$using_saved_copy,
						$is_archived || $using_saved_copy
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</section>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}


	private function selected_data_hub_season($seasons) {
		$requested_slug = isset($_GET['season']) ? sanitize_title(wp_unslash($_GET['season'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ($requested_slug) {
			foreach ($seasons as $season) {
				if ($requested_slug === $season['slug']) {
					return $season;
				}
			}
		}

		foreach ($seasons as $season) {
			if ('current' === $season['status']) {
				return $season;
			}
		}

		return $seasons[0] ?? null;
	}


	private function render_data_hub_season_tabs($seasons, $selected_season) {
		if (!$seasons || !$selected_season) {
			return '';
		}

		$page_id = get_queried_object_id();
		$base_url = $page_id ? get_permalink($page_id) : remove_query_arg('season');
		ob_start();
		?>
		<nav class="dlh-season-tabs" aria-label="<?php echo esc_attr__('Data Hub seasons', 'draft-league-hub'); ?>">
			<?php foreach ($seasons as $season) : ?>
				<?php $is_selected = absint($season['id']) === absint($selected_season['id']); ?>
				<a class="dlh-season-tab<?php echo $is_selected ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('season', $season['slug'], $base_url)); ?>"<?php echo $is_selected ? ' aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<span><?php echo esc_html($season['label']); ?></span>
					<small><?php echo esc_html('current' === $season['status'] ? __('Current', 'draft-league-hub') : __('Archive', 'draft-league-hub')); ?></small>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
		return ob_get_clean();
	}
}
