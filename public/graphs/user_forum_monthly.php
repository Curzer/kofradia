<?php

require "graphs_base.php";
ajax::require_user();
global $_base;

// annen bruker
$u_id = login::$user->id;
$up_name = login::$user->player->data['up_name'];
if (isset($_GET['up_id']) && access::has("mod"))
{
	$up_id = (int) getval("up_id");
	$result = \Kofradia\DB::get()->query("SELECT up_u_id, up_id, up_name FROM users_players WHERE up_id = $up_id");
	if ($result->rowCount() == 0) ajax::text("ERROR:UP-404", ajax::TYPE_404);
	
	$row = $result->fetch();
	$u_id = $row['up_u_id'];
	$up_name = $row['up_name'];
}

// annen måned?
$date = $_base->date->get();
if (isset($_GET['date']))
{
	$d = check_date($_GET['date'], "%y4%m");
	if (!$d) die("Invalid date.");
	
	$date->setDate($d[1], $d[2], 1);
}

// finn tidspunkter
$date->setDate($date->format("Y"), $date->format("n"), 1);
$date->setTime(0, 0, 0);
$time_from = $date->format("U");
$date->modify("+1 month -1 sec");
$time_to = $date->format("U");

// sett opp timestatistikk
$days = $date->format("t");
$month = $date->format(date::FORMAT_MONTH);
$stats1 = array();
$stats2 = array();
$x = array();
for ($i = 1; $i <= $days; $i++)
{
	$stats1[$i] = 0;
	$stats2[$i] = 0;
	$x[] = "$i. ".$month;
}

// hent statistikk
$result = \Kofradia\DB::get()->query("SELECT DAY(FROM_UNIXTIME(ft_time)) AS day, COUNT(ft_id) num FROM forum_topics JOIN users_players ON ft_up_id = up_id WHERE up_u_id = $u_id AND ft_time >= $time_from AND ft_time <= $time_to GROUP BY DAY(FROM_UNIXTIME(ft_time))");
while ($row = $result->fetch())
{
	$stats1[$row['day']] = (int) $row['num'];
}
$result = \Kofradia\DB::get()->query("SELECT DAY(FROM_UNIXTIME(fr_time)) AS day, COUNT(fr_id) num FROM forum_replies JOIN users_players ON fr_up_id = up_id WHERE up_u_id = $u_id AND fr_time >= $time_from AND fr_time <= $time_to GROUP BY DAY(FROM_UNIXTIME(fr_time))");
while ($row = $result->fetch())
{
	$stats2[$row['day']] = (int) $row['num'];
}

$ofc = new OFC();
$ofc->title(new OFC_Title("Aktivitet i forumet for $up_name - ".$date->format(date::FORMAT_MONTH)." ".$date->format("Y")));

$bar = new OFC_Charts_Area();
$bar->text("Antall forumsvar");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# svar");
$bar->values(array_values($stats2));
$bar->colour(OFC_Colours::$colours[1]);
$ofc->add_element($bar);

$bar = new OFC_Charts_Area();
$bar->text("Antall forumtråder");
$bar->dot_style()->type("solid-dot")->dot_size(3)->halo_size(2)->tip("#x_label#<br>#val# tråder");
$bar->values(array_values($stats1));
$bar->colour(OFC_Colours::$colours[0]);
$ofc->add_element($bar);

$ofc->axis_x()->label()->steps(2)->rotate(330)->labels($x);
$ofc->axis_y()->set_numbers(0, max(max($stats1), max($stats2)));

$ofc->dark_colors();
echo $ofc;