<?php
/**
 * Run with: php wp-content/plugins/draft-league-hub/tests/monthly-vote-history.php
 * Uses the existing isolated ballot harness; no database or saved site changes.
 */
if ('cli' !== PHP_SAPI) {
	exit;
}
require __DIR__ . '/monthly-vote-choices.php';
$checks = 0;

function history_ballot($id, $month, $questions, $answers = array(), $close = '2001-01-01 23:59:59') {
	update_post_meta($id, 'dlh_award_month', $month);
	update_post_meta($id, 'dlh_open_until', $close);
	update_post_meta($id, 'dlh_questions', $questions);
	$votes = array();
	foreach ($answers as $voter => $values) {
		foreach ($values as $key => $value) {
			$votes[$voter]['answers'][$key]['value'] = $value;
		}
	}
	update_post_meta($id, 'dlh_votes', $votes);
}

$GLOBALS['dlh_test_ballots'] = array();
check(array() === $test->history(), 'No closed ballots must produce an empty history.');
check(str_contains($test->history_html(), 'No past results yet.'), 'The empty archive must explain when results will appear.');
check('publish' === $GLOBALS['dlh_test_query']['post_status'], 'Draft and private ballots must be excluded by the archive query.');
check(-1 === $GLOBALS['dlh_test_query']['posts_per_page'], 'Older months must not silently disappear behind a query limit.');
$close_query = $GLOBALS['dlh_test_query']['meta_query'][0];
check('dlh_open_until' === $close_query['key'] && '<' === $close_query['compare'] && 'DATETIME' === $close_query['type'], 'The archive query must only request ballots past their close date.');
check(abs(strtotime($close_query['value']) - time()) < 5, 'The close cutoff must use the current WordPress time.');

$quote = '"I meant to bench the hat-trick scorer." — Alex & Sam <b>again</b> ' . str_repeat('Still trusting the process. ', 12);
$questions = $test->parse("Manager of the month|manager\nQuote of the month|text\nBest trade|choice|Salah for Haaland|Palmer for Saka\nUnanswered award|text\nZero option|choice|0|One");
history_ballot(2, '2000-12', $questions, array(
	array('manager-of-the-month' => 7, 'quote-of-the-month' => $quote, 'best-trade' => 'Salah for Haaland', 'zero-option' => '0'),
	array('manager-of-the-month' => 7, 'quote-of-the-month' => $quote, 'best-trade' => 'Palmer for Saka'),
	array('manager-of-the-month' => 8, 'quote-of-the-month' => 'Runner-up quote', 'best-trade' => 'Salah for Haaland'),
	array('manager-of-the-month' => 8, 'quote-of-the-month' => '', 'best-trade' => 'Palmer for Saka'),
	array('manager-of-the-month' => 9, 'quote-of-the-month' => array('malformed'), 'unanswered-award' => ''),
	array('manager-of-the-month' => 0),
));
update_post_meta(2, 'dlh_month', '2001-01');
history_ballot(3, '2001-01', $test->parse('New year award|manager'));
history_ballot(4, '2099-01', $test->parse('Open secret award|text'), array(array('open-secret-award' => 'Hidden nominee')), gmdate('Y-m-d H:i:s', time() + 86400));
history_ballot(5, '2000-10', $questions, array(), 'not a date');
history_ballot(6, '2000-10', $questions, array(), '');
history_ballot(7, '', $test->parse('Legacy award|text'), array(array('legacy-award' => 'Original winner')));
update_post_meta(7, 'dlh_month', '2000-11');
history_ballot(8, '2000-09', array());
$GLOBALS['dlh_test_ballots'] = array(2, 5, 7, 4, 8, 3, 6);

$history = $test->history();
check(array('2001-01', '2000-12', '2000-11') === array_column($history, 'month'), 'History must sort by award month across years and exclude open, invalid, undated, and questionless ballots.');
check('December 2000' === $history[1]['label'], 'The displayed month must be the award month, not the following voting month.');
check('November 2000' === $history[2]['label'], 'Legacy ballots must retain their original calendar month.');
$awards = $history[1]['awards'];
check(array('Manager 7', 'Manager 8') === $awards[0]['winners'] && 2 === $awards[0]['votes'], 'Every tied manager must win, excluding lower totals and abstentions.');
check(array(sanitize_text_field($quote)) === $awards[1]['winners'] && 2 === $awards[1]['votes'], 'The full winning quote must survive without truncation; blank and malformed answers cannot win.');
check(true === $awards[1]['is_quote'], 'Quote nominations must receive quote presentation.');
check(array('Salah for Haaland', 'Palmer for Saka') === $awards[2]['winners'], 'Choice award ties must include every winning option.');
check(array() === $awards[3]['winners'] && 0 === $awards[3]['votes'], 'An unanswered award must not invent a winner.');
check(array(0) === $awards[4]['winners'] && 1 === $awards[4]['votes'], 'The string zero must remain a valid winning choice.');
check(array() === $history[0]['awards'][0]['winners'], 'A closed ballot with no submissions must still show its unanswered awards.');

$html = $test->history_html();
$dom = ballot_dom($html);
check(7 === $dom->query('//tbody/tr')->length, 'The table must contain one row per saved award in each closed ballot.');
check(4 === $dom->query('//thead/tr/th[@scope="col"]')->length, 'The archive must label its month, award, winner, and vote columns.');
check(1 === $dom->query('//blockquote')->length && sanitize_text_field($quote) === $dom->query('//blockquote')->item(0)->textContent, 'The table must render the complete winning quote as text.');
check(!str_contains($html, '<b>again</b>') && str_contains($html, '&amp; Sam'), 'Quote content must be safely escaped.');
check(2 === substr_count($html, 'Joint winners') && 2 === substr_count($html, '2 each'), 'Ties must be clearly labelled and show votes per winner.');
check(2 === substr_count($html, 'No votes cast'), 'Unanswered awards must have an explicit empty state.');
check(!str_contains($html, 'Runner-up quote') && !str_contains($html, 'Manager 9'), 'The archive must show winners rather than all nominees.');
check(!str_contains($html, 'Hidden nominee') && !str_contains($html, 'Open secret award'), 'Open ballot answers and questions must stay out of the archive.');

$test->raw = 'Replacement question|text';
check($history === $test->history(), 'Current default question edits must not affect historical questions or winners.');
$GLOBALS['dlh_test_admin'] = true;
check($history === $test->history(), 'Admin result access must not expose open ballots in the winners archive.');
$GLOBALS['dlh_test_admin'] = false;

history_ballot(9, '2001-02', $test->parse('Quote of the month|choice|First quote|Second quote'), array(
	array('quote-of-the-month' => 'First quote'), array('quote-of-the-month' => 'Second quote'),
));
$GLOBALS['dlh_test_ballots'] = array(9);
$dom = ballot_dom($test->history_html());
check(2 === $dom->query('//blockquote')->length, 'Quote dropdowns must display the full text of every tied winning quote.');

// A just-closed current ballot belongs in history too, even before the month changes.
history_ballot(1, '2001-02', $questions, array(array('quote-of-the-month' => 'Current closed winner')));
$GLOBALS['dlh_test_ballots'] = array(1, 2, 3, 7);
$html = $test->shortcode_monthly_votes();
check(str_contains($html, 'Past Winners') && str_contains($html, 'Current closed winner'), 'The monthly vote shortcode must include the archive and the newly closed current ballot.');
check(!str_contains($html, '<form'), 'Adding history must not reopen a closed voting form.');

echo "Passed {$checks} monthly vote history checks.\n";
