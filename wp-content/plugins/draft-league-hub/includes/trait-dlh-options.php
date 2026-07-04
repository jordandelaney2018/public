<?php
if (!defined('ABSPATH')) {
	exit;
}

trait DLH_Options {


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


	public function maybe_upgrade_content() {
		$content_version = get_option('dlh_content_version', '0.1.0');
		if (version_compare($content_version, '0.2.1', '>=')) {
			return;
		}

		$this->create_default_pages();
		update_option('dlh_content_version', '0.2.1');
		flush_rewrite_rules(false);
	}


	private function create_default_pages() {
		$options = $this->get_options();
		$pages = array(
			'home' => array('title' => 'League Home', 'slug' => 'league-home', 'shortcode' => '[dlh_home]'),
			'news' => array('title' => 'League News', 'slug' => 'league-newsroom', 'shortcode' => '[dlh_news]'),
			'votes' => array('title' => 'Monthly Votes', 'slug' => 'monthly-votes', 'shortcode' => '[dlh_monthly_votes]'),
			'sidebets' => array('title' => 'Sidebets', 'slug' => 'sidebets', 'shortcode' => '[dlh_sidebets]'),
			'hall_of_fame' => array('title' => 'Hall of Fame', 'slug' => 'hall-of-fame', 'shortcode' => '[dlh_hall_of_fame]'),
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
			'hall_of_fame' => __('Hall of Fame', 'draft-league-hub'),
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
}
