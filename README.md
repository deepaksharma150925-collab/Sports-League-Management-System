# Sports League and Tournament Management System

A PHP + MySQL DBMS academic project with a responsive dashboard.

## Requirements
- XAMPP (Apache + MySQL)
- PHP 8.0+ recommended
- MySQL 8.0+ recommended (the SQL uses CHECK constraints, views, triggers and `SIGNAL`)

## Installation (Windows / XAMPP)
1. Extract the `sports_league_management` folder.
2. Copy it to `C:\xampp\htdocs\`.
3. Start **Apache** and **MySQL** in XAMPP Control Panel.
4. Open `http://localhost/phpmyadmin`.
5. Select **Import**, choose `database.sql` from this project and click **Import**.
6. Open `config.php`. The default XAMPP settings are `root` with an empty password. Change these if your MySQL setup differs.
7. Visit `http://localhost/sports_league_management/`.

There is no login screen in this classroom demo; the dashboard opens directly.

## Included modules
- Dashboard overview and quick actions
- CRUD: teams, players, leagues, seasons, tournaments, venues, officials and disciplinary records
- Fixture scheduling with venue-overlap protection
- Result submission and correction using a PDO transaction
- Tournament standings calculated from the `v_team_standings` SQL view
- Player statistics and reports

## DBMS concepts demonstrated
- **Relational modelling:** primary keys, foreign keys, unique constraints and check constraints
- **Joins:** dashboard fixture list, result lists, player/roster reports and standings
- **Aggregate functions:** COUNT, SUM and GROUP BY in reports and standings
- **Views:** `v_team_standings`
- **Triggers:** prevent two matches from being scheduled at the same venue within a three-hour window
- **Transactions:** result submission locks the match row, upserts the result, updates match status and commits; on failure it rolls back
- **Indexes:** match date index and unique constraints

## Add player statistics (SQL example)
Use phpMyAdmin → `sports_league_db` → SQL. Replace the IDs with existing IDs:
```sql
INSERT INTO player_statistics
(match_id, player_id, goals, assists, yellow_cards, red_cards, minutes_played)
VALUES (1, 1, 2, 1, 0, 0, 90);
```
The `player_statistics` table is used by the Player Statistics page. You can also add an official to a match:
```sql
INSERT INTO match_official(match_id, official_id) VALUES (1, 1);
```

## Useful demonstration sequence
1. Open Dashboard and explain the summary cards.
2. Add a team and register a player.
3. Create a tournament and schedule a fixture.
4. Try scheduling another match at the same venue within three hours to demonstrate the trigger.
5. Enter a match result, then correct the same result to demonstrate the transaction/upsert.
6. Open Standings and explain points, goal difference and SQL view.
7. Open Reports and explain JOIN + GROUP BY + aggregate functions.

## Notes / scope
- The application is designed as a local academic demo, not a production deployment.
- The database seed data creates sample teams, players, tournaments, venues and two fixtures.
- The fixture overlap trigger currently applies a simple three-hour venue booking window. Adjust the interval if your sport requires a different match duration.
- Foreign-key restrictions intentionally prevent deleting records that are already referenced by matches/results.
