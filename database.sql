CREATE DATABASE IF NOT EXISTS sports_league_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sports_league_db;

CREATE TABLE sport (
  sport_id INT AUTO_INCREMENT PRIMARY KEY,
  sport_name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255)
);
CREATE TABLE league (
  league_id INT AUTO_INCREMENT PRIMARY KEY,
  league_name VARCHAR(120) NOT NULL,
  sport_id INT NOT NULL,
  format ENUM('League','Knockout','Hybrid') DEFAULT 'League',
  FOREIGN KEY (sport_id) REFERENCES sport(sport_id)
);
CREATE TABLE season (
  season_id INT AUTO_INCREMENT PRIMARY KEY,
  season_name VARCHAR(80) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('Upcoming','Active','Completed') DEFAULT 'Upcoming',
  CHECK (end_date >= start_date)
);
CREATE TABLE tournament (
  tournament_id INT AUTO_INCREMENT PRIMARY KEY,
  league_id INT NOT NULL,
  season_id INT NOT NULL,
  tournament_name VARCHAR(120) NOT NULL,
  start_date DATE,
  end_date DATE,
  status ENUM('Upcoming','Ongoing','Completed') DEFAULT 'Upcoming',
  FOREIGN KEY (league_id) REFERENCES league(league_id),
  FOREIGN KEY (season_id) REFERENCES season(season_id)
);
CREATE TABLE team (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  team_name VARCHAR(100) NOT NULL UNIQUE,
  city VARCHAR(80),
  coach_name VARCHAR(100),
  contact_email VARCHAR(120),
  founded_year YEAR,
  status ENUM('Active','Inactive') DEFAULT 'Active'
);
CREATE TABLE player (
  player_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60),
  dob DATE,
  gender ENUM('Male','Female','Other') DEFAULT 'Other',
  position_name VARCHAR(60),
  jersey_no INT,
  availability ENUM('Available','Unavailable','Injured','Suspended') DEFAULT 'Available',
  email VARCHAR(120),
  UNIQUE(team_id, jersey_no),
  FOREIGN KEY (team_id) REFERENCES team(team_id) ON DELETE SET NULL
);
CREATE TABLE team_roster (
  roster_id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  player_id INT NOT NULL,
  joined_on DATE NOT NULL,
  left_on DATE,
  UNIQUE(team_id, player_id, joined_on),
  FOREIGN KEY (team_id) REFERENCES team(team_id),
  FOREIGN KEY (player_id) REFERENCES player(player_id)
);
CREATE TABLE venue (
  venue_id INT AUTO_INCREMENT PRIMARY KEY,
  venue_name VARCHAR(120) NOT NULL,
  city VARCHAR(80),
  capacity INT DEFAULT 0,
  surface VARCHAR(60)
);
CREATE TABLE official (
  official_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  role_name VARCHAR(60) NOT NULL,
  phone VARCHAR(25),
  email VARCHAR(120)
);
CREATE TABLE matches (
  match_id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  home_team_id INT NOT NULL,
  away_team_id INT NOT NULL,
  venue_id INT,
  match_date DATETIME NOT NULL,
  status ENUM('Scheduled','Completed','Postponed','Cancelled') DEFAULT 'Scheduled',
  CHECK(home_team_id <> away_team_id),
  FOREIGN KEY (tournament_id) REFERENCES tournament(tournament_id),
  FOREIGN KEY (home_team_id) REFERENCES team(team_id),
  FOREIGN KEY (away_team_id) REFERENCES team(team_id),
  FOREIGN KEY (venue_id) REFERENCES venue(venue_id),
  INDEX idx_match_date(match_date)
);
CREATE TABLE match_official (
  match_id INT NOT NULL,
  official_id INT NOT NULL,
  PRIMARY KEY(match_id, official_id),
  FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
  FOREIGN KEY (official_id) REFERENCES official(official_id)
);
CREATE TABLE match_result (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL UNIQUE,
  home_score INT NOT NULL DEFAULT 0,
  away_score INT NOT NULL DEFAULT 0,
  winner_team_id INT NULL,
  result_note VARCHAR(255),
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK(home_score >= 0 AND away_score >= 0),
  FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
  FOREIGN KEY (winner_team_id) REFERENCES team(team_id)
);
CREATE TABLE player_statistics (
  stat_id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  player_id INT NOT NULL,
  goals INT DEFAULT 0,
  assists INT DEFAULT 0,
  yellow_cards INT DEFAULT 0,
  red_cards INT DEFAULT 0,
  minutes_played INT DEFAULT 0,
  UNIQUE(match_id, player_id),
  FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES player(player_id)
);
CREATE TABLE disciplinary_record (
  record_id INT AUTO_INCREMENT PRIMARY KEY,
  player_id INT NOT NULL,
  match_id INT,
  offence VARCHAR(255) NOT NULL,
  action_taken VARCHAR(255),
  record_date DATE NOT NULL,
  FOREIGN KEY (player_id) REFERENCES player(player_id),
  FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE SET NULL
);

-- View: live standings derived from completed match results.
CREATE OR REPLACE VIEW v_team_standings AS
SELECT t.team_id, t.team_name, tr.tournament_id,
       COUNT(*) AS played,
       SUM(CASE WHEN (m.home_team_id=t.team_id AND r.home_score>r.away_score)
                  OR (m.away_team_id=t.team_id AND r.away_score>r.home_score) THEN 1 ELSE 0 END) AS won,
       SUM(CASE WHEN r.home_score=r.away_score THEN 1 ELSE 0 END) AS drawn,
       SUM(CASE WHEN (m.home_team_id=t.team_id AND r.home_score<r.away_score)
                  OR (m.away_team_id=t.team_id AND r.away_score<r.home_score) THEN 1 ELSE 0 END) AS lost,
       SUM(CASE WHEN m.home_team_id=t.team_id THEN r.home_score ELSE r.away_score END) AS goals_for,
       SUM(CASE WHEN m.home_team_id=t.team_id THEN r.away_score ELSE r.home_score END) AS goals_against,
       SUM(CASE WHEN (m.home_team_id=t.team_id AND r.home_score>r.away_score)
                  OR (m.away_team_id=t.team_id AND r.away_score>r.home_score) THEN 3
                WHEN r.home_score=r.away_score THEN 1 ELSE 0 END) AS points
FROM team t
JOIN (SELECT DISTINCT tournament_id, home_team_id AS team_id FROM matches
      UNION SELECT DISTINCT tournament_id, away_team_id AS team_id FROM matches) tr
  ON tr.team_id=t.team_id
JOIN matches m ON m.tournament_id=tr.tournament_id
 AND (m.home_team_id=t.team_id OR m.away_team_id=t.team_id)
JOIN match_result r ON r.match_id=m.match_id
GROUP BY t.team_id,t.team_name,tr.tournament_id;

DELIMITER //
CREATE TRIGGER prevent_venue_overlap
BEFORE INSERT ON matches FOR EACH ROW
BEGIN
  IF NEW.venue_id IS NOT NULL AND EXISTS (
    SELECT 1 FROM matches
    WHERE venue_id=NEW.venue_id AND status NOT IN ('Cancelled','Postponed')
      AND ABS(TIMESTAMPDIFF(MINUTE, match_date, NEW.match_date)) < 180
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Venue is already booked within a 3-hour window.';
  END IF;
END//
CREATE TRIGGER prevent_venue_overlap_update
BEFORE UPDATE ON matches FOR EACH ROW
BEGIN
  IF NEW.venue_id IS NOT NULL AND EXISTS (
    SELECT 1 FROM matches
    WHERE venue_id=NEW.venue_id AND match_id<>OLD.match_id
      AND status NOT IN ('Cancelled','Postponed')
      AND ABS(TIMESTAMPDIFF(MINUTE, match_date, NEW.match_date)) < 180
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Venue is already booked within a 3-hour window.';
  END IF;
END//
DELIMITER ;

-- Sample data
INSERT INTO sport(sport_name,description) VALUES
('Football','Association football'),('Basketball','Indoor team sport'),('Cricket','Bat-and-ball sport');
INSERT INTO league(league_name,sport_id,format) VALUES ('Premier Football League',1,'League'),('City Basketball League',2,'League');
INSERT INTO season(season_name,start_date,end_date,status) VALUES ('2026 Season','2026-01-01','2026-12-31','Active');
INSERT INTO tournament(league_id,season_id,tournament_name,start_date,end_date,status) VALUES
(1,1,'Premier Cup 2026','2026-02-01','2026-11-30','Ongoing'),
(2,1,'City Hoops 2026','2026-03-01','2026-10-31','Ongoing');
INSERT INTO team(team_name,city,coach_name,contact_email,founded_year) VALUES
('Eagles FC','Delhi','Rahul Mehta','eagles@example.com',2010),
('Raptors FC','Noida','Amit Singh','raptors@example.com',2012),
('Lions FC','Gurugram','Neeraj Kumar','lions@example.com',2015),
('Sharks FC','Delhi','Vikas Rao','sharks@example.com',2017);
INSERT INTO player(team_id,first_name,last_name,dob,gender,position_name,jersey_no,availability) VALUES
(1,'Arjun','Verma','2002-04-12','Male','Forward',9,'Available'),
(1,'Kabir','Shah','2001-09-21','Male','Midfielder',8,'Available'),
(2,'Rohan','Das','2002-01-17','Male','Defender',4,'Available'),
(2,'Ayaan','Khan','2003-06-05','Male','Forward',11,'Available'),
(3,'Dev','Malik','2001-12-02','Male','Goalkeeper',1,'Available'),
(4,'Ishaan','Gupta','2002-07-18','Male','Forward',10,'Injured');
INSERT INTO team_roster(team_id,player_id,joined_on) VALUES
(1,1,'2026-01-01'),(1,2,'2026-01-01'),(2,3,'2026-01-01'),(2,4,'2026-01-01'),(3,5,'2026-01-01'),(4,6,'2026-01-01');
INSERT INTO venue(venue_name,city,capacity,surface) VALUES
('Memorial Field','Delhi',25000,'Grass'),('Central Stadium','Noida',18000,'Grass'),('Riverside Arena','Gurugram',12000,'Artificial');
INSERT INTO official(full_name,role_name,phone,email) VALUES
('Sanjay Kapoor','Referee','9876500001','sanjay@example.com'),('Priya Sharma','Assistant Referee','9876500002','priya@example.com');
INSERT INTO matches(tournament_id,home_team_id,away_team_id,venue_id,match_date,status) VALUES
(1,1,2,1,'2026-11-25 16:00:00','Scheduled'),
(1,3,4,2,'2026-11-26 17:00:00','Scheduled');
