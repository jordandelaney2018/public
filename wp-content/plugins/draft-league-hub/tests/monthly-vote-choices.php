<?php
/**
 * Run with: php wp-content/plugins/draft-league-hub/tests/monthly-vote-choices.php
 * Uses WordPress formatting and in-memory ballot storage; no database required.
 */
if ('cli' !== PHP_SAPI) {
	exit;
}

define('ABSPATH', dirname(__DIR__, 4) . '/');
define('WPINC', 'wp-includes');
foreach (array('compat', 'load', 'plugin', 'functions', 'formatting', 'general-template', 'kses', 'class-wp-error') as $file) {
	require_once ABSPATH . WPINC . '/' . $file . '.php';
}
if (file_exists(ABSPATH . WPINC . '/utf8.php')) {
	require_once ABSPATH . WPINC . '/compat-utf8.php';
	require_once ABSPATH . WPINC . '/utf8.php';
}
add_filter('sanitize_title', 'sanitize_title_with_dashes', 10, 3);
add_filter('pre_option', static function ($value, $name) {
	return array('blog_charset' => 'UTF-8', 'timezone_string' => 'UTC', 'date_format' => 'Y-m-d', 'time_format' => 'H:i:s')[$name] ?? '';
}, 10, 2);

// Replace only services requiring WordPress application state.
function __($text, $domain = '') { return $text; }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_attr__($text, $domain = '') { return esc_attr($text); }
function _n($singular, $plural, $count, $domain = '') { return 1 === $count ? $singular : $plural; }
function get_locale() { return 'en_US'; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['dlh_test_meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['dlh_test_meta'][$id][$key] = $value; return true; }
function get_post_type($id) { return 1 === $id ? 'dlh_vote_month' : ''; }
function get_the_title($id) { return 'Test Awards'; }
function is_user_logged_in() { return false; }
function current_user_can($capability) { return false; }
function wp_get_current_user() { return (object) array('ID' => 0, 'display_name' => ''); }
function wp_create_nonce($action) { return 'test-nonce'; }

foreach (array('votes', 'form-handlers', 'shortcodes', 'renderers') as $trait) {
	require_once dirname(__DIR__) . '/includes/trait-dlh-' . $trait . '.php';
}

class DLH_Test_Redirect extends RuntimeException {}

class DLH_Choice_Test {
	use DLH_Votes {
		parse_default_questions as public parse;
		validate_vote_questions as public validate;
		sync_open_vote_questions as public sync;
	}
	use DLH_Form_Handlers;
	use DLH_Shortcodes;
	use DLH_Renderers {
		render_vote_results as public results;
	}

	public $raw = '';
	private function get_options() { return array('default_questions' => $this->raw); }
	private function ensure_current_vote_month() { $this->sync(1); return 1; }
	private function current_vote_key($create = false) { return 'test_voter'; }
	private function verify_nonce_or_die($action) {}
	private function redirect_with_notice($notice) { throw new DLH_Test_Redirect($notice); }
	private function manager_select($name, $selected = 0, $placeholder = '', $id = '') {
		return '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"><option value="7">Manager Seven</option></select>';
	}
	private function manager_name($id) { return 'Manager ' . $id; }

	public function submit($answers) {
		$_POST = array('vote_id' => 1, 'answer' => wp_slash($answers));
		try {
			$this->handle_vote_submission();
		} catch (DLH_Test_Redirect $redirect) {
			return $redirect->getMessage();
		}
		throw new RuntimeException('Submission did not redirect.');
	}
}

$checks = 0;
function check($condition, $message) {
	global $checks;
	if (!$condition) {
		throw new RuntimeException($message);
	}
	$checks++;
}
function ballot_dom($html) {
	$doc = new DOMDocument();
	$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
	return new DOMXPath($doc);
}

$test = new DLH_Choice_Test();
$test->raw = "Best trade|choice| Salah for Haaland | Palmer, Saka & Watkins | O'Brien's \"pick\" |0||Salah for Haaland|<b>Bold</b>\r\nManager award|manager\nQuote award|text";
$questions = $test->parse($test->raw);
check(array('Salah for Haaland', 'Palmer, Saka & Watkins', 'O\'Brien\'s "pick"', '0', 'Bold') === $questions[0]['options'], 'Options must preserve punctuation and zero, strip markup, and ignore blank/duplicate entries.');
check(array('key' => 'manager-award', 'label' => 'Manager award', 'type' => 'manager') === $questions[1], 'Existing manager questions must keep their data format.');
check(array('key' => 'quote-award', 'label' => 'Quote award', 'type' => 'text') === $questions[2], 'Existing text questions must keep their data format.');
check(true === $test->validate($test->raw), 'Valid mixed questions must pass validation.');
check(is_wp_error($test->validate('Empty award|choice| |<b></b>')), 'Choice questions without usable options must be rejected.');
check(true === $test->validate('Zero award|choice|0'), 'A zero option must count as an option.');

update_post_meta(1, 'dlh_open_until', gmdate('Y-m-d H:i:s', time() + 86400));
update_post_meta(1, 'dlh_votes', array());
check($test->sync(1), 'Open ballots must receive question options.');
$html = $test->shortcode_monthly_votes();
$dom = ballot_dom($html);
check(1 === $dom->query('//select[@id="answer-best-trade"]')->length, 'Custom answers must render as a labelled dropdown.');
check(6 === $dom->query('//select[@id="answer-best-trade"]/option')->length, 'The dropdown must contain its placeholder and all five options.');
check(1 === $dom->query('//select[@id="answer-manager-award"]')->length, 'Manager answers must remain dropdowns.');
check(1 === $dom->query('//input[@id="answer-quote-award" and @type="text"]')->length, 'Text answers must remain text fields.');
check(str_contains($html, 'O&#039;Brien&#039;s &quot;pick&quot;'), 'Option text and values must be escaped.');

$answers = array('best-trade' => 'O\'Brien\'s "pick"', 'manager-award' => '7', 'quote-award' => 'A nomination');
check('vote_saved' === $test->submit($answers), 'A listed choice must save alongside existing answer types.');
$votes = get_post_meta(1, 'dlh_votes', true);
check('O\'Brien\'s "pick"' === $votes['test_voter']['answers']['best-trade']['value'], 'Selected option punctuation must survive saving.');
check(7 === $votes['test_voter']['answers']['manager-award']['value'], 'Manager IDs must retain their existing integer format.');
check('A nomination' === $votes['test_voter']['answers']['quote-award']['value'], 'Text nominations must keep their existing value.');
$dom = ballot_dom($test->shortcode_monthly_votes());
check('O\'Brien\'s "pick"' === $dom->query('//select[@id="answer-best-trade"]/option[@selected]')->item(0)->getAttribute('value'), 'Saved votes must be selected when reopening the ballot.');
check(str_contains($test->results($questions, $votes), '<span>O&#039;Brien&#039;s &quot;pick&quot;</span><strong>1</strong>'), 'Choice votes must be counted and escaped in results.');

foreach (array(array('best-trade' => 'Unlisted'), array('best-trade' => array('Salah for Haaland')), 'malformed') as $invalid) {
	check('invalid_vote_answer' === $test->submit($invalid), 'Unlisted and malformed answers must be rejected.');
	check($votes === get_post_meta(1, 'dlh_votes', true), 'Rejected submissions must not overwrite a saved vote.');
}

check('vote_saved' === $test->submit(array('best-trade' => '0')), 'A numeric-looking option must save.');
check(str_contains($test->results($questions, get_post_meta(1, 'dlh_votes', true)), '<span>0</span><strong>1</strong>'), 'A zero option must appear in results.');
check('vote_saved' === $test->submit(array('best-trade' => '')), 'Choice questions must allow abstention like existing questions.');
check('' === get_post_meta(1, 'dlh_votes', true)['test_voter']['answers']['best-trade']['value'], 'Updating a vote must allow clearing the choice.');
check('vote_saved' === $test->submit($answers), 'Voters must be able to update their selection.');
check(1 === count(get_post_meta(1, 'dlh_votes', true)), 'Updating a vote must not add another voter.');

$test->raw = "Best trade|choice|0|O'Brien's \"pick\"|Salah for Haaland";
$test->sync(1);
$dom = ballot_dom($test->shortcode_monthly_votes());
check('O\'Brien\'s "pick"' === $dom->query('//select[@id="answer-best-trade"]/option[@selected]')->item(0)->getAttribute('value'), 'Reordering options must not change a saved selection.');
$test->raw = 'Best trade|choice|New option';
$test->sync(1);
$votes = get_post_meta(1, 'dlh_votes', true);
$dom = ballot_dom($test->shortcode_monthly_votes());
check(1 === $dom->query('//select[@id="answer-best-trade"]/option[@selected and @disabled]')->length, 'Removed selections must be identified when reopening a ballot.');
check(str_contains($test->results(get_post_meta(1, 'dlh_questions', true), $votes), 'O&#039;Brien&#039;s &quot;pick&quot;'), 'Editing options must retain historical votes in results.');
check('invalid_vote_answer' === $test->submit($answers), 'A stale form must not submit an option that has been removed.');
check($votes === get_post_meta(1, 'dlh_votes', true), 'Rejecting a stale form must retain the existing vote.');

update_post_meta(1, 'dlh_open_until', '2000-01-01 00:00:00');
$closed_questions = get_post_meta(1, 'dlh_questions', true);
$test->raw = 'Replacement award|choice|A|B';
check(false === $test->sync(1), 'Closed ballots must not sync new question options.');
check($closed_questions === get_post_meta(1, 'dlh_questions', true), 'Closed ballots must retain their original questions and options.');
check('vote_closed' === $test->submit(array('best-trade' => 'New option')), 'Closed ballots must reject submissions.');
check(!str_contains($test->shortcode_monthly_votes(), '<form'), 'Closed ballots must show results without a voting form.');

echo "Passed {$checks} monthly vote choice checks.\n";
