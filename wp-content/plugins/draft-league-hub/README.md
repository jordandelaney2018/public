# Draft League Hub

A lightweight WordPress plugin for a Premier League FPL Draft mini-league site.

## What It Adds

- Manager directory in the WordPress dashboard.
- League News custom post type for joke stories and matchday slander.
- Auto-generated monthly vote page with a past winners table.
- Sidebets page with front-end submissions.
- Hall of Fame gallery with a CMS-managed Past Winners tab.
- Calendar page for upcoming draft dates, deadlines, and league events.
- Cached FPL Draft API standings widget.
- Season records and locally stored FPL Draft snapshots for safe annual rollover.
- Season tabs and a complete draft recap with the order, original squads, and full board.
- Season-aware Groupie Picks rounds with fractional odds and a public win-percentage leaderboard.
- A season-aware 12-team Draft Cup with a random draw and knockout bracket.

Past Winner cards serve the original uploaded photo rather than a recompressed
WordPress derivative. For sharp cards, use portrait originals at least
800 × 1200 pixels; genuinely low-resolution source images still need replacing.

## Install

1. Zip the `draft-league-hub` folder, or upload the provided `draft-league-hub.zip`.
2. In WordPress, go to Plugins > Add New > Upload Plugin.
3. Activate Draft League Hub.
4. Go to Settings > Draft League Hub.
5. Save your league name and create the starter pages.
6. Add managers under Managers.
7. Add the generated pages to your WordPress menu.

## Season Rollover

The plugin creates a current season record from the existing season label and
FPL Draft league ID. Viewing the stats page saves successful API responses,
including the completed draft choices, to that season automatically. The
settings page can also sync manager names, team names, and entry IDs from the
current FPL Draft league.

Before changing to a new league, go to Settings > Draft League Hub and use
**Archive current and start new season**. The plugin captures the outgoing
season first and cancels the rollover if the standings cannot be saved.

If the current season has the correct label but was linked to the wrong FPL
Draft league, use **Reset current season data** instead. The protected reset
requires the replacement league ID and an exact confirmation phrase. It clears
only that season's saved Data Hub snapshot, Groupie Picks rounds, Draft Cup,
and FPL API cache. Managers and unrelated CMS content are retained.

## Shortcodes

- `[dlh_home]` - front-page hero and latest news.
- `[dlh_news]` - league news listing.
- `[dlh_monthly_votes]` - current monthly vote and past winners.
- `[dlh_sidebets]` - sidebet board and submission form.
- `[dlh_hall_of_fame]` - gallery and Past Winners tabs.
- `[dlh_calendar]` - upcoming draft dates.
- `[dlh_stats]` - seasonal Data Hub, FPL Draft standings, and saved draft recap.
- `[dlh_group_picks]` - Groupie Picks leaderboard and round history.
- `[dlh_draft_cup]` - Draft Cup bracket and results.

## Monthly Award Options

In **Settings > Draft League Hub > Monthly vote questions**, enter one award per
line. The supported formats are:

```text
Manager of the month|manager
Quote of the month|text
Best trade of the month|choice|Salah for Haaland|Palmer for Saka|Watkins for Isak
```

Use `choice` to show a dropdown containing your own answer options. Separate each
option with `|`; commas are allowed inside options. Blank and duplicate options
are ignored, and each choice question must contain at least one option. Voters
can select one option or leave the answer blank, and can update their vote while
the ballot is open.

Changes apply to the current open ballot and future months. Closed ballots keep
their original questions and options. If an option is renamed or removed after
voting starts, votes for its original label remain in the results until those
voters update their votes. Keep the award title unchanged to retain its connection
to existing answers.

The **Past Winners** table appears below the current ballot and results. It
automatically lists published, closed ballots with the newest award month first,
using each ballot's saved questions and votes. Each row shows the month, award,
winning manager or nomination, and winning vote count. Quote awards include the
full winning quote as entered (include the speaker in the nomination if wanted).
Ties show all joint winners; awards with no nominations show **No votes cast**.
Open ballots stay out of the archive, including for admins. Older ballots without
an award-month field use their original calendar month.

To run the isolated voting regression checks from the WordPress root, use
`php wp-content/plugins/draft-league-hub/tests/monthly-vote-choices.php` and
`php wp-content/plugins/draft-league-hub/tests/monthly-vote-history.php`.
These checks use WordPress formatting functions and in-memory ballot storage;
they do not need a running database or change saved site data.

## Groupie Picks

Open **Managers > Groupie Picks** to add a round. Each round has a title, date,
optional gameweek, and one pick, fractional-odds, and result row for every
manager. Saved rounds can be edited later to add missing odds. Pending and void
picks stay in the history but only wins and losses count towards win percentage.
Average odds include every pick with a recorded price. Managers with fewer than
three graded picks are labelled provisional.

## Draft Cup

Open **Managers > Draft Cup**, choose a starting gameweek, and create the draw.
Eight managers play in the opening round while four receive random byes. The
quarter-finals, semi-finals, and final follow over the next three gameweeks.

Use **Refresh scores from FPL** after matches begin. Winners are advanced only
after a gameweek is marked finished by FPL. Manual score and tie-break controls
are available if the API is unavailable or a tie finishes level. The daily
maintenance task also checks an active cup automatically.

## FPL Draft API

The stats shortcode uses:

- `https://draft.premierleague.com/api/league/{leagueId}/details`

The plugin caches responses using WordPress transients. If the endpoint returns `403`, your league data may require authentication or may be temporarily processing on the FPL side.

Other Draft endpoints visible in the current FPL Draft frontend include:

- `/api/bootstrap-static`
- `/api/draft/league/{leagueId}/transactions`
- `/api/draft/league/{leagueId}/trades`
- `/api/draft/entry/{entryId}/transactions`

Those are good candidates for future widgets such as trade history, waiver activity, and award nominations based on actual transfers.

## Notes

Monthly votes are created automatically for the current month when the vote page is viewed, and once a day by WP-Cron. Changes to the default questions update the current open ballot and apply to future months. Closed ballots keep their original question set.
