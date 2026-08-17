# Draft League Hub

A lightweight WordPress plugin for a Premier League FPL Draft mini-league site.

## What It Adds

- Manager directory in the WordPress dashboard.
- League News custom post type for joke stories and matchday slander.
- Auto-generated monthly vote page.
- Sidebets page with front-end submissions.
- Calendar page for upcoming draft dates, deadlines, and league events.
- Cached FPL Draft API standings widget.
- Season records and locally stored FPL Draft snapshots for safe annual rollover.
- Season tabs and a complete draft recap with the order, original squads, and full board.
- Season-aware group picks rounds with a public win-percentage leaderboard.

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

## Shortcodes

- `[dlh_home]` - front-page hero and latest news.
- `[dlh_news]` - league news listing.
- `[dlh_monthly_votes]` - current monthly vote.
- `[dlh_sidebets]` - sidebet board and submission form.
- `[dlh_calendar]` - upcoming draft dates.
- `[dlh_stats]` - seasonal Data Hub, FPL Draft standings, and saved draft recap.
- `[dlh_group_picks]` - group picks leaderboard and round history.

## Group Picks

Open **Managers > Group Picks** to add a round. Each round has a title, date,
optional gameweek, and one pick/result row for every manager. Pending and void
picks stay in the history but only wins and losses count towards win percentage.
Managers with fewer than three graded picks are labelled provisional.

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

Monthly votes are created automatically for the current month when the vote page is viewed, and once a day by WP-Cron. Existing monthly votes keep their original question set, so changes to default questions apply to future months.
