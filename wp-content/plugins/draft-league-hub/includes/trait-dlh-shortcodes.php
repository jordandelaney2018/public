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
							<?php if (in_array($key, array('votes', 'sidebets', 'hall_of_fame', 'calendar', 'stats'), true)) : ?>
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
			<?php if (!empty($user_vote['submitted'])) : ?>
				<div class="dlh-notice">
					<?php echo esc_html(sprintf(__('Your vote was last saved on %s.', 'draft-league-hub'), mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $user_vote['submitted']))); ?>
				</div>
			<?php endif; ?>

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


	public function shortcode_hall_of_fame($atts = array()) {
		$atts = shortcode_atts(array('count' => 24), $atts, 'dlh_hall_of_fame');
		$query = new WP_Query(
			array(
				'post_type' => 'dlh_hof_entry',
				'post_status' => 'publish',
				'posts_per_page' => max(1, absint($atts['count'])),
				'orderby' => 'date',
				'order' => 'DESC',
			)
		);

		ob_start();
		?>
		<div class="dlh-wrap dlh-section">
			<div class="dlh-section__head">
				<div>
					<h2><?php echo esc_html__('Hall of Fame', 'draft-league-hub'); ?></h2>
					<p><?php echo esc_html__('The permanent record of league nonsense.', 'draft-league-hub'); ?></p>
				</div>
				<span class="dlh-pill"><?php echo esc_html(sprintf(_n('%d entry', '%d entries', $query->found_posts, 'draft-league-hub'), $query->found_posts)); ?></span>
			</div>

			<div class="dlh-hof-grid">
				<?php if ($query->have_posts()) : ?>
					<?php while ($query->have_posts()) : ?>
						<?php $query->the_post(); ?>
						<?php echo $this->render_hall_of_fame_card(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<div class="dlh-empty"><?php echo esc_html__('No Hall of Fame entries yet. The evidence locker is empty.', 'draft-league-hub'); ?></div>
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
}
