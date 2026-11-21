<?php
session_start();
require __DIR__ . '/config.php';

$modules = [
 'teams'=>['title'=>'Teams','table'=>'team','pk'=>'team_id','fields'=>['team_name'=>'Team name','city'=>'City','coach_name'=>'Coach','contact_email'=>'Email','founded_year'=>'Founded year','status'=>'Status']],
 'players'=>['title'=>'Players','table'=>'player','pk'=>'player_id','fields'=>['team_id'=>'Team ID','first_name'=>'First name','last_name'=>'Last name','dob'=>'Date of birth','gender'=>'Gender','position_name'=>'Position','jersey_no'=>'Jersey number','availability'=>'Availability','email'=>'Email']],
 'leagues'=>['title'=>'Leagues','table'=>'league','pk'=>'league_id','fields'=>['league_name'=>'League name','sport_id'=>'Sport ID','format'=>'Format']],
 'seasons'=>['title'=>'Seasons','table'=>'season','pk'=>'season_id','fields'=>['season_name'=>'Season name','start_date'=>'Start date','end_date'=>'End date','status'=>'Status']],
 'tournaments'=>['title'=>'Tournaments','table'=>'tournament','pk'=>'tournament_id','fields'=>['league_id'=>'League ID','season_id'=>'Season ID','tournament_name'=>'Tournament name','start_date'=>'Start date','end_date'=>'End date','status'=>'Status']],
 'venues'=>['title'=>'Venues','table'=>'venue','pk'=>'venue_id','fields'=>['venue_name'=>'Venue name','city'=>'City','capacity'=>'Capacity','surface'=>'Surface']],
 'officials'=>['title'=>'Officials','table'=>'official','pk'=>'official_id','fields'=>['full_name'=>'Full name','role_name'=>'Role','phone'=>'Phone','email'=>'Email']],
 'discipline'=>['title'=>'Disciplinary records','table'=>'disciplinary_record','pk'=>'record_id','fields'=>['player_id'=>'Player ID','match_id'=>'Match ID (optional)','offence'=>'Offence','action_taken'=>'Action taken','record_date'=>'Date']]
];
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function go($url){header("Location: $url");exit;}
$notice=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  $action=$_POST['action']??'';
  if($action==='save' && isset($modules[$_POST['module']??''])) {
   $key=$_POST['module']; $m=$modules[$key]; $data=[];
   foreach($m['fields'] as $f=>$label){$v=trim($_POST[$f]??'');$data[$f]=($v===''?null:$v);}
   $id=(int)($_POST['id']??0);
   if($id){$sets=implode(',',array_map(fn($f)=>"$f = ?",array_keys($data)));$st=$pdo->prepare("UPDATE {$m['table']} SET $sets WHERE {$m['pk']}=?");$st->execute([...array_values($data),$id]);}
   else {$cols=implode(',',array_keys($data));$marks=implode(',',array_fill(0,count($data),'?'));$st=$pdo->prepare("INSERT INTO {$m['table']} ($cols) VALUES ($marks)");$st->execute(array_values($data));}
   go("?page=$key&saved=1");
  }
  if($action==='delete' && isset($modules[$_POST['module']??''])) {
   $m=$modules[$_POST['module']];$st=$pdo->prepare("DELETE FROM {$m['table']} WHERE {$m['pk']}=?");$st->execute([(int)$_POST['id']]);go("?page={$_POST['module']}&deleted=1");
  }
  if($action==='fixture'){
   $st=$pdo->prepare("INSERT INTO matches(tournament_id,home_team_id,away_team_id,venue_id,match_date,status) VALUES(?,?,?,?,?,'Scheduled')");
   $st->execute([(int)$_POST['tournament_id'],(int)$_POST['home_team_id'],(int)$_POST['away_team_id'],($_POST['venue_id']?:null),str_replace('T',' ',$_POST['match_date']).':00']);
   go("?page=fixtures&saved=1");
  }
  if($action==='result'){
   $pdo->beginTransaction();
   $mid=(int)$_POST['match_id'];$hs=(int)$_POST['home_score'];$as=(int)$_POST['away_score'];
   $q=$pdo->prepare("SELECT home_team_id,away_team_id FROM matches WHERE match_id=? FOR UPDATE");$q->execute([$mid]);$match=$q->fetch();
   if(!$match) throw new Exception("Match not found.");
   $winner=$hs>$as?$match['home_team_id']:($as>$hs?$match['away_team_id']:null);
   $q=$pdo->prepare("INSERT INTO match_result(match_id,home_score,away_score,winner_team_id,result_note) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE home_score=VALUES(home_score),away_score=VALUES(away_score),winner_team_id=VALUES(winner_team_id),result_note=VALUES(result_note)");
   $q->execute([$mid,$hs,$as,$winner,trim($_POST['result_note']??'')]);
   $pdo->prepare("UPDATE matches SET status='Completed' WHERE match_id=?")->execute([$mid]);
   $pdo->commit();go("?page=results&saved=1");
  }
 } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
$page=$_GET['page']??'dashboard';
if(!in_array($page,array_merge(['dashboard','fixtures','results','standings','statistics','reports'],array_keys($modules))))$page='dashboard';
if(isset($_GET['saved']))$notice='Record saved successfully.';
if(isset($_GET['deleted']))$notice='Record deleted successfully.';
$counts=[];
foreach(['team'=>'Teams','player'=>'Players','matches'=>'Matches','venue'=>'Venues'] as $t=>$n){$counts[$n]=(int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Sports League Manager</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="layout"><aside class="sidebar"><div class="brand"><span class="brand-icon">🏆</span><div><b>SPORTS</b><small>LEAGUE MANAGER</small></div></div>
<nav>
<a class="<?= $page==='dashboard'?'active':''?>" href="?page=dashboard">▦ Dashboard</a>
<div class="nav-label">MANAGEMENT</div>
<?php foreach(['teams'=>'Teams','players'=>'Players','leagues'=>'Leagues','seasons'=>'Seasons','tournaments'=>'Tournaments','venues'=>'Venues','officials'=>'Officials'] as $k=>$v):?><a class="<?= $page===$k?'active':''?>" href="?page=<?=$k?>">◈ <?=h($v)?></a><?php endforeach;?>
<div class="nav-label">MATCH CENTRE</div>
<a class="<?= $page==='fixtures'?'active':''?>" href="?page=fixtures">▣ Fixtures & Schedule</a><a class="<?= $page==='results'?'active':''?>" href="?page=results">⚑ Match Results</a><a class="<?= $page==='standings'?'active':''?>" href="?page=standings">☷ Standings</a><a class="<?= $page==='statistics'?'active':''?>" href="?page=statistics">▤ Player Statistics</a><a class="<?= $page==='discipline'?'active':''?>" href="?page=discipline">⚠ Discipline</a><a class="<?= $page==='reports'?'active':''?>" href="?page=reports">▧ Reports</a>
</nav><div class="sidebar-bottom">● System Online<br><small>DBMS Project · 2026</small></div></aside>
<main class="main"><header class="topbar"><div><span class="muted">Sports League /</span> <b><?=h(ucwords(str_replace('_',' ',$page)))?></b></div><div class="profile"><span class="avatar">A</span><span>Administrator<br><small>League Manager</small></span></div></header>
<div class="content">
<?php if($notice):?><div class="alert success"><?=h($notice)?></div><?php endif;?><?php if($error):?><div class="alert danger"><?=h($error)?></div><?php endif;?>
<?php if($page==='dashboard'):?>
<div class="page-heading"><div><h1>Dashboard Overview</h1><p>Welcome back! Here's what's happening in your sports league.</p></div><a class="btn" href="?page=fixtures">＋ Schedule match</a></div>
<div class="stats-grid"><?php foreach($counts as $label=>$num):?><div class="stat-card"><div class="stat-icon"><?=['Teams'=>'👥','Players'=>'🏃','Matches'=>'📅','Venues'=>'🏟️'][$label]?></div><div><span><?=$label?></span><strong><?=$num?></strong></div></div><?php endforeach;?></div>
<div class="two-col"><section class="panel"><div class="panel-head"><h2>Upcoming Fixtures</h2><a href="?page=fixtures">View all →</a></div><?php $rows=$pdo->query("SELECT m.match_id,m.match_date,h.team_name home,a.team_name away,v.venue_name FROM matches m JOIN team h ON h.team_id=m.home_team_id JOIN team a ON a.team_id=m.away_team_id LEFT JOIN venue v ON v.venue_id=m.venue_id WHERE m.status='Scheduled' ORDER BY m.match_date LIMIT 5")->fetchAll();if(!$rows):?><p class="empty">No upcoming fixtures.</p><?php else:foreach($rows as $r):?><div class="fixture-row"><div class="team-name"><?=h($r['home'])?></div><span class="vs">VS</span><div class="team-name"><?=h($r['away'])?></div><small><?=h(date('d M, H:i',strtotime($r['match_date'])))?> · <?=h($r['venue_name']??'Venue TBD')?></small></div><?php endforeach;endif;?></section>
<section class="panel"><div class="panel-head"><h2>Quick Actions</h2></div><div class="quick-grid"><a href="?page=teams">＋ Add team</a><a href="?page=players">＋ Register player</a><a href="?page=fixtures">＋ Create fixture</a><a href="?page=results">＋ Enter result</a><a href="?page=standings">☷ View standings</a><a href="?page=reports">▤ View reports</a></div></section></div>
<section class="panel"><div class="panel-head"><h2>Recent Match Results</h2><a href="?page=results">All results →</a></div><?php $rs=$pdo->query("SELECT h.team_name home,a.team_name away,r.home_score,r.away_score,m.match_date FROM match_result r JOIN matches m ON m.match_id=r.match_id JOIN team h ON h.team_id=m.home_team_id JOIN team a ON a.team_id=m.away_team_id ORDER BY m.match_date DESC LIMIT 5")->fetchAll();?><div class="table-wrap"><table><tr><th>Home team</th><th>Score</th><th>Away team</th><th>Date</th></tr><?php foreach($rs as $r):?><tr><td><?=h($r['home'])?></td><td><b><?=h($r['home_score'])?> - <?=h($r['away_score'])?></b></td><td><?=h($r['away'])?></td><td><?=h($r['match_date'])?></td></tr><?php endforeach;?></table></div></section>
<?php elseif(isset($modules[$page])): $m=$modules[$page];$edit=null;if(isset($_GET['edit'])){$q=$pdo->prepare("SELECT * FROM {$m['table']} WHERE {$m['pk']}=?");$q->execute([(int)$_GET['edit']]);$edit=$q->fetch();} $rows=$pdo->query("SELECT * FROM {$m['table']} ORDER BY {$m['pk']} DESC LIMIT 200")->fetchAll();?>
<div class="page-heading"><div><h1><?=h($m['title'])?></h1><p>Add, update and manage <?=strtolower(h($m['title']))?> records.</p></div></div>
<div class="panel"><h2><?=$edit?'Update':'Add new'?> <?=h(rtrim($m['title'],'s'))?></h2><form method="post" class="form-grid"><input type="hidden" name="action" value="save"><input type="hidden" name="module" value="<?=h($page)?>"><input type="hidden" name="id" value="<?=h($edit[$m['pk']]??'')?>"><?php foreach($m['fields'] as $f=>$label):?><label><?=h($label)?><?php $type=(str_contains($f,'date')||$f==='dob')?'date':(str_contains($f,'email')?'email':'text');?><input name="<?=h($f)?>" type="<?=$type?>" value="<?=h($edit[$f]??'')?>" <?=in_array($f,['team_name','first_name','league_name','season_name','tournament_name','venue_name','full_name','offence'])?'required':''?>></label><?php endforeach;?><div class="form-actions"><button class="btn">Save record</button><?php if($edit):?><a class="btn secondary" href="?page=<?=$page?>">Cancel</a><?php endif;?></div></form></div>
<div class="panel"><div class="panel-head"><h2>All <?=h($m['title'])?></h2><span class="muted"><?=count($rows)?> records</span></div><div class="table-wrap"><table><thead><tr><th>ID</th><?php foreach($m['fields'] as $label):?><th><?=h($label)?></th><?php endforeach;?><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=$r[$m['pk']]?></td><?php foreach($m['fields'] as $f=>$label):?><td><?=h($r[$f]??'—')?></td><?php endforeach;?><td class="actions"><a href="?page=<?=$page?>&edit=<?=$r[$m['pk']]?>">Edit</a><form method="post" onsubmit="return confirm('Delete this record?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="module" value="<?=$page?>"><input type="hidden" name="id" value="<?=$r[$m['pk']]?>"><button class="link danger-text">Delete</button></form></td></tr><?php endforeach;?></tbody></table></div></div>
<?php elseif($page==='fixtures'):?>
<div class="page-heading"><div><h1>Fixtures & Schedule</h1><p>Create matches and allocate venues. Venue conflicts are blocked by a database trigger.</p></div></div>
<div class="panel"><h2>Schedule a match</h2><form method="post" class="form-grid"><input type="hidden" name="action" value="fixture">
<label>Tournament<select name="tournament_id" required><?php foreach($pdo->query("SELECT tournament_id,tournament_name FROM tournament") as $r):?><option value="<?=$r['tournament_id']?>"><?=h($r['tournament_name'])?></option><?php endforeach;?></select></label>
<label>Home team<select name="home_team_id" required><?php foreach($pdo->query("SELECT team_id,team_name FROM team") as $r):?><option value="<?=$r['team_id']?>"><?=h($r['team_name'])?></option><?php endforeach;?></select></label>
<label>Away team<select name="away_team_id" required><?php foreach($pdo->query("SELECT team_id,team_name FROM team") as $r):?><option value="<?=$r['team_id']?>"><?=h($r['team_name'])?></option><?php endforeach;?></select></label>
<label>Venue<select name="venue_id"><option value="">Venue TBD</option><?php foreach($pdo->query("SELECT venue_id,venue_name FROM venue") as $r):?><option value="<?=$r['venue_id']?>"><?=h($r['venue_name'])?></option><?php endforeach;?></select></label>
<label>Date & time<input type="datetime-local" name="match_date" required></label><div class="form-actions"><button class="btn">Create fixture</button></div></form></div>
<div class="panel"><h2>All fixtures</h2><?php $rows=$pdo->query("SELECT m.*,t.tournament_name,h.team_name home,a.team_name away,v.venue_name FROM matches m JOIN tournament t ON t.tournament_id=m.tournament_id JOIN team h ON h.team_id=m.home_team_id JOIN team a ON a.team_id=m.away_team_id LEFT JOIN venue v ON v.venue_id=m.venue_id ORDER BY m.match_date DESC")->fetchAll();?><div class="table-wrap"><table><tr><th>ID</th><th>Tournament</th><th>Fixture</th><th>Date</th><th>Venue</th><th>Status</th></tr><?php foreach($rows as $r):?><tr><td><?=$r['match_id']?></td><td><?=h($r['tournament_name'])?></td><td><?=h($r['home'])?> vs <?=h($r['away'])?></td><td><?=h($r['match_date'])?></td><td><?=h($r['venue_name']??'TBD')?></td><td><span class="pill"><?=h($r['status'])?></span></td></tr><?php endforeach;?></table></div></div>
<?php elseif($page==='results'):?>
<div class="page-heading"><div><h1>Match Results</h1><p>Submit or correct a result. The result and match status update in one database transaction.</p></div></div>
<div class="panel"><h2>Submit / correct result</h2><form method="post" class="form-grid"><input type="hidden" name="action" value="result"><label>Match<select name="match_id" required><?php foreach($pdo->query("SELECT m.match_id,m.match_date,h.team_name home,a.team_name away FROM matches m JOIN team h ON h.team_id=m.home_team_id JOIN team a ON a.team_id=m.away_team_id ORDER BY m.match_date DESC") as $r):?><option value="<?=$r['match_id']?>">#<?=$r['match_id']?> · <?=h($r['home'])?> vs <?=h($r['away'])?> · <?=h($r['match_date'])?></option><?php endforeach;?></select></label><label>Home score<input type="number" min="0" name="home_score" required></label><label>Away score<input type="number" min="0" name="away_score" required></label><label>Result note<input name="result_note" placeholder="Optional note"></label><div class="form-actions"><button class="btn">Save result</button></div></form></div>
<div class="panel"><h2>Recorded results</h2><?php $rows=$pdo->query("SELECT m.match_id,h.team_name home,a.team_name away,r.home_score,r.away_score,r.result_note,r.recorded_at FROM match_result r JOIN matches m ON m.match_id=r.match_id JOIN team h ON h.team_id=m.home_team_id JOIN team a ON a.team_id=m.away_team_id ORDER BY m.match_date DESC")->fetchAll();?><div class="table-wrap"><table><tr><th>Match</th><th>Score</th><th>Note</th><th>Last updated</th></tr><?php foreach($rows as $r):?><tr><td><?=h($r['home'])?> vs <?=h($r['away'])?></td><td><b><?=$r['home_score']?> - <?=$r['away_score']?></b></td><td><?=h($r['result_note'])?></td><td><?=h($r['recorded_at'])?></td></tr><?php endforeach;?></table></div></div>
<?php elseif($page==='standings'):?>
<div class="page-heading"><div><h1>Tournament Standings</h1><p>Automatically calculated from recorded match results (3 points for a win, 1 for a draw).</p></div></div>
<?php foreach($pdo->query("SELECT tournament_id,tournament_name FROM tournament ORDER BY tournament_name") as $tour):?><div class="panel"><h2><?=h($tour['tournament_name'])?></h2><?php $q=$pdo->prepare("SELECT *,(goals_for-goals_against) goal_difference FROM v_team_standings WHERE tournament_id=? ORDER BY points DESC,goal_difference DESC,goals_for DESC");$q->execute([$tour['tournament_id']]);$rows=$q->fetchAll();?><div class="table-wrap"><table><tr><th>Rank</th><th>Team</th><th>Played</th><th>Won</th><th>Drawn</th><th>Lost</th><th>GF</th><th>GA</th><th>GD</th><th>Points</th></tr><?php $i=1;foreach($rows as $r):?><tr><td><?=$i++?></td><td><b><?=h($r['team_name'])?></b></td><td><?=$r['played']?></td><td><?=$r['won']?></td><td><?=$r['drawn']?></td><td><?=$r['lost']?></td><td><?=$r['goals_for']?></td><td><?=$r['goals_against']?></td><td><?=$r['goal_difference']?></td><td><b><?=$r['points']?></b></td></tr><?php endforeach;?></table></div><?php if(!$rows):?><p class="empty">Standings will appear after results are entered.</p><?php endif;?></div><?php endforeach;?>
<?php elseif($page==='statistics'):?>
<div class="page-heading"><div><h1>Player Statistics</h1><p>Goals, assists and discipline totals by player.</p></div></div><div class="panel"><h2>Player performance summary</h2><?php $rows=$pdo->query("SELECT p.player_id,CONCAT(p.first_name,' ',p.last_name) player,t.team_name,COALESCE(SUM(s.goals),0) goals,COALESCE(SUM(s.assists),0) assists,COALESCE(SUM(s.yellow_cards),0) yellow_cards,COALESCE(SUM(s.red_cards),0) red_cards,COALESCE(SUM(s.minutes_played),0) minutes FROM player p LEFT JOIN team t ON t.team_id=p.team_id LEFT JOIN player_statistics s ON s.player_id=p.player_id GROUP BY p.player_id,player,t.team_name ORDER BY goals DESC")->fetchAll();?><div class="table-wrap"><table><tr><th>Player</th><th>Team</th><th>Goals</th><th>Assists</th><th>Yellow</th><th>Red</th><th>Minutes</th></tr><?php foreach($rows as $r):?><tr><td><?=h($r['player'])?></td><td><?=h($r['team_name']??'Unassigned')?></td><td><?=$r['goals']?></td><td><?=$r['assists']?></td><td><?=$r['yellow_cards']?></td><td><?=$r['red_cards']?></td><td><?=$r['minutes']?></td></tr><?php endforeach;?></table></div><p class="hint">To add match-level player statistics, insert rows into <code>player_statistics</code> using the SQL examples in README.md.</p></div>
<?php elseif($page==='reports'):?>
<div class="page-heading"><div><h1>Reports & Analytics</h1><p>Quick operational reports generated using SQL aggregate functions and joins.</p></div></div>
<div class="stats-grid"><?php $reportQueries=[['Total goals',"SELECT COALESCE(SUM(home_score+away_score),0) FROM match_result"],['Completed matches',"SELECT COUNT(*) FROM matches WHERE status='Completed'"],['Available players',"SELECT COUNT(*) FROM player WHERE availability='Available'"],['Active tournaments',"SELECT COUNT(*) FROM tournament WHERE status='Ongoing'"]];foreach($reportQueries as [$label,$sql]):?><div class="stat-card"><div class="stat-icon">📊</div><div><span><?=h($label)?></span><strong><?=$pdo->query($sql)->fetchColumn()?></strong></div></div><?php endforeach;?></div>
<div class="panel"><h2>Team roster report</h2><?php $rows=$pdo->query("SELECT t.team_name,COUNT(p.player_id) players,SUM(CASE WHEN p.availability='Available' THEN 1 ELSE 0 END) available FROM team t LEFT JOIN player p ON p.team_id=t.team_id GROUP BY t.team_id,t.team_name ORDER BY players DESC")->fetchAll();?><div class="table-wrap"><table><tr><th>Team</th><th>Total players</th><th>Available players</th></tr><?php foreach($rows as $r):?><tr><td><?=h($r['team_name'])?></td><td><?=$r['players']?></td><td><?=$r['available']??0?></td></tr><?php endforeach;?></table></div></div>
<?php endif;?>
<footer>© 2026 Sports League & Tournament Management System <span>DBMS Academic Project</span></footer>
</div></main></div></body></html>
