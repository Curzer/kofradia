<?php

global $_base;
$time = time();

// TODO: diskforbruk

// p�logget siste 24 timer
$_base->db->query("
	INSERT INTO stats_time (st_type, st_time, st_value)
	SELECT 'online_86400', $time, COUNT(*) FROM users_players WHERE up_last_online >= $time-86400");

// p�logget siste 12 timer
$_base->db->query("
	INSERT INTO stats_time (st_type, st_time, st_value)
	SELECT 'online_43200', $time, COUNT(*) FROM users_players WHERE up_last_online >= $time-43200");

// p�logget siste 6 timer
$_base->db->query("
	INSERT INTO stats_time (st_type, st_time, st_value)
	SELECT 'online_21600', $time, COUNT(*) FROM users_players WHERE up_last_online >= $time-21600");