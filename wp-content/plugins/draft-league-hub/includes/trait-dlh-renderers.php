<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Renderers {


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
					echo '<div class="dlh-card__image">' . get_the_post_thumbnail(get_the_ID(), 'medium_large') . '</div>';
				}
				echo '<div class="dlh-card__body">';
				echo '<p class="dlh-kicker">' . esc_html(get_the_date()) . '</p>';
				echo '<h3>' . esc_html(get_the_title()) . '</h3>';
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
		$stipulation = get_post_meta($post_id, 'dlh_stipulation', true);
		$statuses = $this->sidebet_statuses();

		ob_start();
		echo '<article class="dlh-card dlh-sidebet">';
		echo '<div class="dlh-card__body">';
		echo '<div class="dlh-card__meta"><span class="dlh-pill">' . esc_html($statuses[$status] ?? __('Active', 'draft-league-hub')) . '</span>';
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
		if (current_user_can('edit_post', $post_id)) {
			echo $this->render_sidebet_controls($post_id, $status, $winner); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></article>';

		return ob_get_clean();
	}


	private function render_sidebet_controls($post_id, $status, $winner) {
		ob_start();
		echo '<form class="dlh-card-actions" method="post" action="">';
		echo '<input type="hidden" name="dlh_action" value="update_sidebet">';
		echo '<input type="hidden" name="sidebet_id" value="' . esc_attr($post_id) . '">';
		wp_nonce_field('dlh_update_sidebet', 'dlh_nonce');
		echo '<label><span>' . esc_html__('Status', 'draft-league-hub') . '</span><select name="sidebet_status">';
		foreach ($this->sidebet_statuses() as $key => $label) {
			echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__('Winner', 'draft-league-hub') . '</span>' . $this->manager_select('sidebet_winner', $winner, __('No winner yet', 'draft-league-hub')) . '</label>';
		echo '<button class="dlh-button" type="submit">' . esc_html__('Update', 'draft-league-hub') . '</button>';
		echo '</form>';

		return ob_get_clean();
	}


	private function render_hall_of_fame_card($post_id) {
		$media_type = get_post_meta($post_id, 'dlh_media_type', true);
		$media_type = in_array($media_type, array('image', 'video'), true) ? $media_type : 'image';
		$media_url = get_post_meta($post_id, 'dlh_media_url', true);
		$attachment_id = absint(get_post_meta($post_id, 'dlh_media_attachment_id', true));

		ob_start();
		echo '<article class="dlh-card dlh-hof-card">';
		echo '<div class="dlh-hof-card__media">';
		if ($attachment_id && wp_attachment_is_image($attachment_id)) {
			echo wp_get_attachment_image($attachment_id, 'large', false, array('alt' => get_the_title($post_id))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ($attachment_id && 0 === strpos((string) get_post_mime_type($attachment_id), 'video/') && wp_get_attachment_url($attachment_id)) {
			echo '<video controls src="' . esc_url(wp_get_attachment_url($attachment_id)) . '"></video>';
		} elseif ('video' === $media_type && $media_url) {
			$embed = wp_oembed_get($media_url);
			if ($embed) {
				echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<video controls src="' . esc_url($media_url) . '"></video>';
			}
		} elseif (has_post_thumbnail($post_id)) {
			echo get_the_post_thumbnail($post_id, 'large'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ($media_url) {
			echo '<img src="' . esc_url($media_url) . '" alt="' . esc_attr(get_the_title($post_id)) . '">';
		} else {
			echo '<div class="dlh-hof-card__placeholder">' . esc_html__('No media yet', 'draft-league-hub') . '</div>';
		}
		echo '</div>';
		echo '<div class="dlh-card__body">';
		echo '<p class="dlh-kicker">' . esc_html(get_the_date('', $post_id)) . '</p>';
		echo '<h3>' . esc_html(get_the_title($post_id)) . '</h3>';
		$caption = get_the_excerpt($post_id);
		if (!$caption) {
			$caption = wp_strip_all_tags(get_post_field('post_content', $post_id));
		}
		if ($caption) {
			echo '<p>' . esc_html(wp_trim_words($caption, 28)) . '</p>';
		}
		echo '</div></article>';

		return ob_get_clean();
	}


	private function render_past_winner_card($post_id) {
		$year = get_post_meta($post_id, 'dlh_winner_year', true);
		$name = get_the_title($post_id);

		ob_start();
		echo '<article class="dlh-winner-card">';
		echo '<div class="dlh-winner-card__photo">';
		$thumbnail_id = get_post_thumbnail_id($post_id);
		$full_image = $thumbnail_id ? wp_get_attachment_image_src($thumbnail_id, 'full') : false;
		if ($full_image) {
			echo '<img src="' . esc_url($full_image[0]) . '" width="' . esc_attr($full_image[1]) . '" height="' . esc_attr($full_image[2]) . '" alt="' . esc_attr($name) . '" loading="lazy" decoding="async">';
		} else {
			echo '<div class="dlh-winner-card__placeholder">' . esc_html__('Photo to follow', 'draft-league-hub') . '</div>';
		}
		if ($year) {
			echo '<span class="dlh-winner-card__year">' . esc_html($year) . '</span>';
		}
		echo '</div>';
		echo '<div class="dlh-winner-card__body"><p class="dlh-kicker">' . esc_html__('League champion', 'draft-league-hub') . '</p><h3>' . esc_html($name) . '</h3></div>';
		echo '</article>';

		return ob_get_clean();
	}


	private function render_calendar_event($post_id) {
		$event_date = get_post_meta($post_id, 'dlh_event_date', true);
		$event_time = get_post_meta($post_id, 'dlh_event_time', true);
		$event_location = get_post_meta($post_id, 'dlh_event_location', true);
		$event_label = get_post_meta($post_id, 'dlh_event_label', true);
		$timezone = wp_timezone();
		$date = DateTime::createFromFormat('Y-m-d', $event_date, $timezone);
		$day = $date ? $date->format('d') : '--';
		$month = $date ? $date->format('M') : __('TBC', 'draft-league-hub');
		$weekday = $date ? wp_date('l', $date->getTimestamp()) : __('Date TBC', 'draft-league-hub');
		$full_date = $date ? wp_date(get_option('date_format'), $date->getTimestamp()) : __('Date TBC', 'draft-league-hub');
		$notes = get_post_field('post_content', $post_id);

		ob_start();
		echo '<article class="dlh-card dlh-calendar-event">';
		echo '<div class="dlh-calendar-event__date" aria-hidden="true">';
		echo '<span>' . esc_html($month) . '</span>';
		echo '<strong>' . esc_html($day) . '</strong>';
		echo '</div>';
		echo '<div class="dlh-calendar-event__body">';
		if ($event_label) {
			echo '<p class="dlh-kicker">' . esc_html($event_label) . '</p>';
		}
		echo '<h3>' . esc_html(get_the_title($post_id)) . '</h3>';
		echo '<div class="dlh-calendar-event__meta">';
		echo '<span>' . esc_html($weekday . ', ' . $full_date) . '</span>';
		if ($event_location) {
			echo '<span>' . esc_html($event_location) . '</span>';
		}
		echo '</div>';
		if ($notes) {
			echo '<p>' . esc_html(wp_trim_words(wp_strip_all_tags($notes), 32)) . '</p>';
		}
		echo '</div>';
		echo '</article>';

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


	private function render_draft_recap($details, $draft, $bootstrap, $season = array()) {
		$choices = $draft['choices'] ?? array();
		if (!$choices || !is_array($choices)) {
			return '<div class="dlh-empty">' . esc_html__('The completed draft choices are not available for this season yet.', 'draft-league-hub') . '</div>';
		}

		$choices = array_values(
			array_filter(
				$choices,
				function($choice) {
					return is_array($choice) && !empty($choice['entry']) && !empty($choice['element']);
				}
			)
		);
		usort(
			$choices,
			function($left, $right) {
				return absint($left['index'] ?? 0) <=> absint($right['index'] ?? 0);
			}
		);

		if (!$choices) {
			return '<div class="dlh-empty">' . esc_html__('The completed draft choices are not available for this season yet.', 'draft-league-hub') . '</div>';
		}

		$player_map = $this->build_player_map($bootstrap['elements'] ?? array());
		$manager_groups = array();
		$rounds = array();
		$auto_pick_count = 0;
		$max_round = 0;
		$max_pick = 0;
		$enriched_choices = array();

		foreach ($choices as $choice) {
			$entry_id = absint($choice['entry'] ?? 0);
			$round = max(1, absint($choice['round'] ?? 0));
			$pick = max(1, absint($choice['pick'] ?? 0));
			$choice['player_name'] = $this->draft_choice_player_name($choice, $player_map);
			$choice['manager_name'] = $this->draft_choice_manager_name($choice);
			$enriched_choices[] = $choice;
			$rounds[$round][$pick] = $choice;
			$max_round = max($max_round, $round);
			$max_pick = max($max_pick, $pick);
			if (!empty($choice['was_auto'])) {
				$auto_pick_count++;
			}

			if (!isset($manager_groups[$entry_id])) {
				$manager_groups[$entry_id] = array(
					'entry_id' => $entry_id,
					'team_name' => sanitize_text_field($choice['entry_name'] ?? ''),
					'manager_name' => $choice['manager_name'],
					'draft_slot' => 0,
					'choices' => array(),
				);
			}

			if (1 === $round) {
				$manager_groups[$entry_id]['draft_slot'] = $pick;
			}
			$manager_groups[$entry_id]['choices'][] = $choice;
		}
		$choices = $enriched_choices;

		$manager_groups = array_values($manager_groups);
		usort(
			$manager_groups,
			function($left, $right) {
				return absint($left['draft_slot']) <=> absint($right['draft_slot']);
			}
		);

		$first_choice = $choices[0];
		$last_choice = $choices[count($choices) - 1];
		$first_time = strtotime($first_choice['choice_time'] ?? '');
		$last_time = strtotime($last_choice['choice_time'] ?? '');
		$duration_minutes = $first_time && $last_time ? max(1, (int) ceil(($last_time - $first_time) / MINUTE_IN_SECONDS)) : 0;
		$draft_date = $first_time ? wp_date('j M Y', $first_time) : __('Draft night', 'draft-league-hub');
		$draft_id = absint($first_choice['draft'] ?? 0);
		$summary_cards = array(
			array(
				'label' => __('Draft night', 'draft-league-hub'),
				'value' => $draft_date,
				'detail' => $duration_minutes ? sprintf(_n('%d minute', '%d minutes', $duration_minutes, 'draft-league-hub'), $duration_minutes) : '',
			),
			array(
				'label' => __('The board', 'draft-league-hub'),
				'value' => sprintf(__('%d picks', 'draft-league-hub'), count($choices)),
				'detail' => sprintf(_n('%d round', '%d rounds', $max_round, 'draft-league-hub'), $max_round),
			),
			array(
				'label' => __('First overall', 'draft-league-hub'),
				'value' => $first_choice['player_name'],
				'detail' => $first_choice['entry_name'] ?? '',
			),
			array(
				'label' => __('Final pick', 'draft-league-hub'),
				'value' => $last_choice['player_name'],
				'detail' => $last_choice['entry_name'] ?? '',
			),
			array(
				'label' => __('Auto-picks', 'draft-league-hub'),
				'value' => $auto_pick_count,
				'detail' => $auto_pick_count ? __('Selections made by the timer', 'draft-league-hub') : __('Everyone beat the clock', 'draft-league-hub'),
			),
		);

		ob_start();
		?>
		<section class="dlh-draft-recap" aria-labelledby="dlh-draft-recap-title">
			<div class="dlh-draft-recap__head">
				<div>
					<p class="dlh-kicker"><?php echo esc_html(sprintf(__('%1$s draft%2$s', 'draft-league-hub'), $season['label'] ?? '', $draft_id ? ' · #' . $draft_id : '')); ?></p>
					<h3 id="dlh-draft-recap-title"><?php echo esc_html__('Draft Recap', 'draft-league-hub'); ?></h3>
					<p><?php echo esc_html__('The original snake draft, saved before waivers and trades start rewriting history.', 'draft-league-hub'); ?></p>
				</div>
				<span class="dlh-draft-recap__saved"><?php echo esc_html(sprintf(_n('%d manager', '%d managers', count($manager_groups), 'draft-league-hub'), count($manager_groups))); ?></span>
			</div>

			<div class="dlh-stats-grid dlh-draft-stats">
				<?php foreach ($summary_cards as $card) : ?>
					<article class="dlh-stat-card">
						<p class="dlh-kicker"><?php echo esc_html($card['label']); ?></p>
						<strong><?php echo esc_html($card['value']); ?></strong>
						<?php if ($card['detail']) : ?><span><?php echo esc_html($card['detail']); ?></span><?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>

			<section class="dlh-draft-section" aria-labelledby="dlh-draft-order-title">
				<div class="dlh-draft-section__head">
					<div>
						<h4 id="dlh-draft-order-title"><?php echo esc_html__('Draft order & opening picks', 'draft-league-hub'); ?></h4>
						<p><?php echo esc_html__('The first-round order and each manager’s opening three selections.', 'draft-league-hub'); ?></p>
					</div>
				</div>
				<div class="dlh-table-wrap dlh-draft-order">
					<table class="dlh-table">
						<thead><tr><th scope="col"><?php echo esc_html__('Slot', 'draft-league-hub'); ?></th><th scope="col"><?php echo esc_html__('Manager', 'draft-league-hub'); ?></th><th scope="col"><?php echo esc_html__('Team', 'draft-league-hub'); ?></th><th scope="col"><?php echo esc_html__('Opening three', 'draft-league-hub'); ?></th></tr></thead>
						<tbody>
							<?php foreach ($manager_groups as $manager) : ?>
								<?php $opening_picks = array_slice(array_column($manager['choices'], 'player_name'), 0, 3); ?>
								<tr>
									<td><strong>#<?php echo esc_html($manager['draft_slot']); ?></strong></td>
									<td><?php echo esc_html($manager['manager_name']); ?></td>
									<td><?php echo esc_html($manager['team_name']); ?></td>
									<td><?php echo esc_html(implode(' · ', $opening_picks)); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="dlh-draft-section" aria-labelledby="dlh-original-squads-title">
				<div class="dlh-draft-section__head">
					<div>
						<h4 id="dlh-original-squads-title"><?php echo esc_html__('Original squads', 'draft-league-hub'); ?></h4>
						<p><?php echo esc_html__('Open a team to see all 15 players in the order they were drafted.', 'draft-league-hub'); ?></p>
					</div>
				</div>
				<div class="dlh-draft-squad-grid">
					<?php foreach ($manager_groups as $manager) : ?>
						<details class="dlh-draft-squad">
							<summary>
								<span><strong><?php echo esc_html($manager['team_name']); ?></strong><small><?php echo esc_html($manager['manager_name']); ?></small></span>
								<b>#<?php echo esc_html($manager['draft_slot']); ?></b>
							</summary>
							<ol>
								<?php foreach ($manager['choices'] as $choice) : ?>
									<li>
										<span><?php echo esc_html(sprintf(__('R%d', 'draft-league-hub'), absint($choice['round']))); ?></span>
										<strong><?php echo esc_html($choice['player_name']); ?></strong>
										<?php if (!empty($choice['was_auto'])) : ?><small><?php echo esc_html__('Auto', 'draft-league-hub'); ?></small><?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ol>
						</details>
					<?php endforeach; ?>
				</div>
			</section>

			<details class="dlh-draft-board" open>
				<summary><span><?php echo esc_html__('Full draft board', 'draft-league-hub'); ?></span><small><?php echo esc_html(sprintf(__('%1$d rounds · %2$d selections', 'draft-league-hub'), $max_round, count($choices))); ?></small></summary>
				<div class="dlh-table-wrap">
					<table class="dlh-table">
						<thead>
							<tr><th scope="col"><?php echo esc_html__('Round', 'draft-league-hub'); ?></th><?php for ($pick = 1; $pick <= $max_pick; $pick++) : ?><th scope="col"><?php echo esc_html(sprintf(__('Pick %d', 'draft-league-hub'), $pick)); ?></th><?php endfor; ?></tr>
						</thead>
						<tbody>
							<?php for ($round = 1; $round <= $max_round; $round++) : ?>
								<tr>
									<th scope="row"><?php echo esc_html(sprintf(__('Round %d', 'draft-league-hub'), $round)); ?></th>
									<?php for ($pick = 1; $pick <= $max_pick; $pick++) : ?>
										<?php $choice = $rounds[$round][$pick] ?? array(); ?>
										<td>
											<?php if ($choice) : ?>
												<small>#<?php echo esc_html($choice['index']); ?> · <?php echo esc_html($choice['entry_name']); ?></small>
												<strong><?php echo esc_html($choice['player_name']); ?></strong>
												<?php if (!empty($choice['was_auto'])) : ?><em><?php echo esc_html__('Auto-pick', 'draft-league-hub'); ?></em><?php endif; ?>
											<?php else : ?>&mdash;<?php endif; ?>
										</td>
									<?php endfor; ?>
								</tr>
							<?php endfor; ?>
						</tbody>
					</table>
				</div>
			</details>
		</section>
		<?php
		return ob_get_clean();
	}


	private function draft_choice_player_name($choice, $player_map) {
		$player_id = absint($choice['element'] ?? 0);
		if ($player_id && isset($player_map[$player_id])) {
			return $this->player_name($player_map, $player_id);
		}

		return $player_id ? sprintf(__('Player #%d', 'draft-league-hub'), $player_id) : __('Unknown player', 'draft-league-hub');
	}


	private function draft_choice_manager_name($choice) {
		$name = trim(($choice['player_first_name'] ?? '') . ' ' . ($choice['player_last_name'] ?? ''));
		return $name ? $name : __('Unknown manager', 'draft-league-hub');
	}


	private function render_standings($details, $transactions = array(), $trades = array(), $bootstrap = array(), $allow_live_extremes = true, $is_saved_copy = false) {
		$league = $details['league']['name'] ?? '';
		$entries = $details['league_entries'] ?? array();
		$entry_maps = $this->build_entry_maps($entries);
		$league_entry_map = $entry_maps['league'];
		$public_entry_map = $entry_maps['public'];
		$player_map = $this->build_player_map($bootstrap['elements'] ?? array());

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

		$latest_event = $this->latest_available_gameweek($bootstrap['events'] ?? array());
		echo $this->render_fpl_stat_cards($details, $standings, $league_entry_map, $public_entry_map, $player_map, $transactions, $trades, $allow_live_extremes, $latest_event); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<div class="dlh-table-wrap"><table class="dlh-table">';
		echo '<thead><tr><th>' . esc_html__('Rank', 'draft-league-hub') . '</th><th>' . esc_html__('Team', 'draft-league-hub') . '</th><th>' . esc_html__('Manager', 'draft-league-hub') . '</th><th>' . esc_html__('GW', 'draft-league-hub') . '</th><th>' . esc_html__('Total', 'draft-league-hub') . '</th></tr></thead><tbody>';

		foreach ($standings as $index => $row) {
			if (!is_array($row)) {
				continue;
			}

			$entry_id = $row['league_entry'] ?? ($row['entry'] ?? ($row['entry_id'] ?? ($row['id'] ?? 0)));
			$entry = $this->entry_from_map($league_entry_map, $entry_id);
			$rank = $row['rank'] ?? ($row['position'] ?? ($index + 1));
			$team = $row['entry_name'] ?? $this->entry_team_name($entry);
			$manager = $row['player_name'] ?? $this->entry_manager_name($entry);
			$event_points = $row['event_total'] ?? '';
			$total_points = $row['total'] ?? ($row['points'] ?? '');

			echo '<tr>';
			echo '<td>' . esc_html($rank) . '</td>';
			echo '<td>' . esc_html($team) . '</td>';
			echo '<td>' . esc_html($manager ? $manager : '-') . '</td>';
			echo '<td>' . esc_html($event_points) . '</td>';
			echo '<td>' . esc_html($total_points) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '<p class="dlh-footnote">' . esc_html($is_saved_copy ? __('This table is stored locally as part of the season archive.', 'draft-league-hub') : __('Data is cached to keep the FPL Draft API happy.', 'draft-league-hub')) . '</p>';

		return ob_get_clean();
	}


	private function render_fpl_stat_cards($details, $standings, $league_entry_map, $public_entry_map, $player_map, $transactions_data, $trades_data, $allow_live_extremes = true, $latest_event = 0) {
		$transactions = $transactions_data['transactions'] ?? array();
		$trades = $trades_data['trades'] ?? array();
		$accepted_transactions = array_values(
			array_filter(
				is_array($transactions) ? $transactions : array(),
				function($transaction) {
					return is_array($transaction) && 'a' === ($transaction['result'] ?? '');
				}
			)
		);
		$trade_rows = is_array($trades) ? $trades : array();
		$cards = array();

		$season_extremes = $allow_live_extremes ? $this->season_gameweek_extremes($details, $public_entry_map, $latest_event) : array('high' => null, 'low' => null);
		if (!empty($season_extremes['high'])) {
			$cards[] = array(
				'label' => __('Season GW High', 'draft-league-hub'),
				'value' => $season_extremes['high']['points'],
				'detail' => sprintf('%s, GW%d', $season_extremes['high']['team'], $season_extremes['high']['event']),
			);
		}

		if (!empty($season_extremes['low'])) {
			$cards[] = array(
				'label' => __('Season GW Low', 'draft-league-hub'),
				'value' => $season_extremes['low']['points'],
				'detail' => sprintf('%s, GW%d', $season_extremes['low']['team'], $season_extremes['low']['event']),
			);
		}

		$cards = array_merge(
			$cards,
			$this->transaction_stat_cards($accepted_transactions, $public_entry_map, $player_map),
			$this->trade_stat_cards($trade_rows, $public_entry_map, $player_map)
		);

		if (empty($cards)) {
			return '';
		}

		ob_start();
		echo '<div class="dlh-stats-grid">';
		foreach ($cards as $card) {
			echo '<article class="dlh-stat-card">';
			echo '<p class="dlh-kicker">' . esc_html($card['label']) . '</p>';
			echo '<strong>' . esc_html($card['value']) . '</strong>';
			if (!empty($card['detail'])) {
				echo '<span>' . esc_html($card['detail']) . '</span>';
			}
			echo '</article>';
		}
		echo '</div>';

		return ob_get_clean();
	}


	private function transaction_stat_cards($transactions, $entry_map, $player_map) {
		$by_entry = array();
		$adds = array();
		$drops = array();
		$by_event = array();

		foreach ($transactions as $transaction) {
			$entry_id = absint($transaction['entry'] ?? 0);
			$event = absint($transaction['event'] ?? 0);
			$element_in = absint($transaction['element_in'] ?? 0);
			$element_out = absint($transaction['element_out'] ?? 0);
			$this->increment_count($by_entry, (string) $entry_id);
			$this->increment_count($by_event, (string) $event);
			$this->increment_count($adds, (string) $element_in);
			$this->increment_count($drops, (string) $element_out);
		}

		$cards = array();
		$top_entry = $this->top_count($by_entry);
		if ($top_entry) {
			$cards[] = array('label' => __('Most Moves', 'draft-league-hub'), 'value' => $top_entry['count'], 'detail' => $this->entry_team_name($this->entry_from_map($entry_map, $top_entry['key'])));
		}

		$top_add = $this->top_count($adds);
		if ($top_add) {
			$cards[] = array('label' => __('Most Added Player', 'draft-league-hub'), 'value' => $top_add['count'], 'detail' => $this->player_name($player_map, $top_add['key']));
		}

		$top_drop = $this->top_count($drops);
		if ($top_drop) {
			$cards[] = array('label' => __('Most Dropped Player', 'draft-league-hub'), 'value' => $top_drop['count'], 'detail' => $this->player_name($player_map, $top_drop['key']));
		}

		$top_event = $this->top_count($by_event);
		if ($top_event) {
			$cards[] = array('label' => __('Busiest Gameweek', 'draft-league-hub'), 'value' => 'GW' . $top_event['key'], 'detail' => sprintf(_n('%d move', '%d moves', $top_event['count'], 'draft-league-hub'), $top_event['count']));
		}

		return $cards;
	}


	private function trade_stat_cards($trades, $entry_map, $player_map) {
		$offers = array();
		$involved = array();
		$players = array();

		foreach ($trades as $trade) {
			if (!is_array($trade)) {
				continue;
			}

			$offered = absint($trade['offered_entry'] ?? 0);
			$received = absint($trade['received_entry'] ?? 0);
			$this->increment_count($offers, (string) $offered);
			$this->increment_count($involved, (string) $offered);
			$this->increment_count($involved, (string) $received);

			foreach (($trade['tradeitem_set'] ?? array()) as $item) {
				if (!is_array($item)) {
					continue;
				}
				$this->increment_count($players, (string) absint($item['element_in'] ?? 0));
				$this->increment_count($players, (string) absint($item['element_out'] ?? 0));
			}
		}

		$cards = array();
		$top_offer = $this->top_count($offers);
		if ($top_offer) {
			$cards[] = array('label' => __('Most Trade Offers', 'draft-league-hub'), 'value' => $top_offer['count'], 'detail' => $this->entry_team_name($this->entry_from_map($entry_map, $top_offer['key'])));
		}

		$top_involved = $this->top_count($involved);
		if ($top_involved) {
			$cards[] = array('label' => __('Most Trade Involvement', 'draft-league-hub'), 'value' => $top_involved['count'], 'detail' => $this->entry_team_name($this->entry_from_map($entry_map, $top_involved['key'])));
		}

		$top_player = $this->top_count($players);
		if ($top_player) {
			$cards[] = array('label' => __('Most Traded Player', 'draft-league-hub'), 'value' => $top_player['count'], 'detail' => $this->player_name($player_map, $top_player['key']));
		}

		return $cards;
	}


	private function latest_available_gameweek($events) {
		$latest = 0;
		foreach ((array) $events as $event) {
			if (!is_array($event) || (empty($event['finished']) && empty($event['is_current']))) {
				continue;
			}

			$latest = max($latest, absint($event['id'] ?? 0));
		}

		return $latest;
	}


	private function season_gameweek_extremes($details, $entry_map, $latest_event = 0) {
		$league = $details['league'] ?? array();
		$league_id = absint($league['id'] ?? 0);
		$start_event = max(1, absint($league['start_event'] ?? 1));
		$league_stop_event = max($start_event, absint($league['stop_event'] ?? 38));
		$stop_event = min($league_stop_event, absint($latest_event));
		if ($stop_event < $start_event) {
			return array('high' => null, 'low' => null);
		}
		$cache_key = 'dlh_season_gw_extremes_' . md5($league_id . ':' . $start_event . ':' . $stop_event . ':' . count($entry_map));
		$cached = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$extremes = array(
			'high' => null,
			'low' => null,
		);
		$entries = array();

		foreach ($entry_map as $entry) {
			$entry_id = absint($entry['entry_id'] ?? ($entry['id'] ?? 0));
			if ($entry_id) {
				$entries[$entry_id] = $entry;
			}
		}

		foreach (range($start_event, $stop_event) as $event) {
			$live = $this->api_get('/api/event/' . rawurlencode($event) . '/live');
			if (is_wp_error($live) || empty($live['elements']) || !is_array($live['elements'])) {
				continue;
			}

			foreach ($entries as $entry_id => $entry) {
				$entry_id = absint($entry['entry_id'] ?? ($entry['id'] ?? 0));
				if (!$entry_id) {
					continue;
				}

				$entry_event = $this->api_get('/api/entry/' . rawurlencode($entry_id) . '/event/' . rawurlencode($event));
				if (is_wp_error($entry_event)) {
					continue;
				}

				$points = $this->entry_event_starting_points($entry_event, $live['elements']);
				if (null === $points) {
					continue;
				}

				$record = array(
					'points' => $points,
					'event' => $event,
					'team' => $this->entry_team_name($entry),
				);

				if (null === $extremes['high'] || $points > $extremes['high']['points']) {
					$extremes['high'] = $record;
				}

				if (null === $extremes['low'] || $points < $extremes['low']['points']) {
					$extremes['low'] = $record;
				}
			}
		}

		set_transient($cache_key, $extremes, 12 * HOUR_IN_SECONDS);
		return $extremes;
	}


	private function entry_event_starting_points($entry_event, $live_elements) {
		$picks = $entry_event['picks'] ?? array();
		if (empty($picks) || !is_array($picks)) {
			return null;
		}

		$total = 0;
		$has_points = false;
		foreach ($picks as $pick) {
			$position = absint($pick['position'] ?? 0);
			if (!$position || $position > 11) {
				continue;
			}

			$element_id = absint($pick['element'] ?? 0);
			if (!$element_id || !isset($live_elements[$element_id])) {
				continue;
			}

			$multiplier = intval($pick['multiplier'] ?? 1);
			$player_points = intval($live_elements[$element_id]['stats']['total_points'] ?? 0);
			$total += $player_points * $multiplier;
			$has_points = true;
		}

		return $has_points ? $total : null;
	}


	private function build_entry_maps($entries) {
		$maps = array(
			'league' => array(),
			'public' => array(),
		);

		foreach ($entries as $entry) {
			$league_entry_id = absint($entry['id'] ?? 0);
			$public_entry_id = absint($entry['entry_id'] ?? 0);

			if ($league_entry_id) {
				$maps['league'][$league_entry_id] = $entry;
			}

			if ($public_entry_id) {
				$maps['public'][$public_entry_id] = $entry;
			}
		}

		return $maps;
	}


	private function build_player_map($players) {
		$map = array();
		foreach ($players as $player) {
			$id = absint($player['id'] ?? 0);
			if ($id) {
				$map[$id] = $player;
			}
		}

		return $map;
	}


	private function entry_from_map($entry_map, $entry_id) {
		$entry_id = absint($entry_id);
		return $entry_id && isset($entry_map[$entry_id]) ? $entry_map[$entry_id] : array();
	}


	private function entry_team_name($entry) {
		return $entry['entry_name'] ?? ($entry['name'] ?? __('Unknown team', 'draft-league-hub'));
	}


	private function entry_manager_name($entry) {
		return trim(($entry['player_first_name'] ?? '') . ' ' . ($entry['player_last_name'] ?? ''));
	}


	private function player_name($player_map, $player_id) {
		$player_id = absint($player_id);
		$player = $player_id && isset($player_map[$player_id]) ? $player_map[$player_id] : array();
		return $player['web_name'] ?? trim(($player['first_name'] ?? '') . ' ' . ($player['second_name'] ?? '')) ?: __('Unknown player', 'draft-league-hub');
	}


	private function increment_count(&$counts, $key) {
		if (!$key || '0' === $key) {
			return;
		}

		if (!isset($counts[$key])) {
			$counts[$key] = 0;
		}
		$counts[$key]++;
	}


	private function top_count($counts) {
		if (empty($counts)) {
			return null;
		}

		arsort($counts);
		$key = array_key_first($counts);
		return array('key' => $key, 'count' => $counts[$key]);
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
			'sidebet_pending' => __('Sidebet submitted for approval.', 'draft-league-hub'),
			'sidebet_updated' => __('Sidebet updated. The record has been amended.', 'draft-league-hub'),
			'sidebet_update_denied' => __('You cannot update that sidebet.', 'draft-league-hub'),
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
