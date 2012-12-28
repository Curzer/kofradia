<?php

/*
 * Setter ned v�pentreningen
 * Kj�res 1 gang per time
 * 
 * Hvis en spiller har under 25 % v�pentrening mister spilleren v�penet (om det er bedre v�pen enn glock)
 */

// sett ned v�pentreningen
$expire = time() - 172800; // kun for de som har v�rt aktive siste 48 timer
ess::$b->db->query("
	UPDATE users_players
	SET up_weapon_training = GREATEST(0.1, up_weapon_training * 0.988)
	WHERE up_weapon_training > 0.1
		AND up_last_online > $expire");

// hent de spillerene som skal nedgradere eller miste v�penet sitt
$result = ess::$b->db->query("
	SELECT up_id, up_name, up_weapon_id, up_weapon_bullets
	FROM users_players
	WHERE up_weapon_training < 0.25 AND up_weapon_id > 1 AND up_access_level != 0");

while ($row = mysql_fetch_assoc($result))
{
	if (!isset(weapon::$weapons[$row['up_weapon_id']])) continue;
	$w = &weapon::$weapons[$row['up_weapon_id']];
	
	// fjern fra evt. auksjoner
	auksjon::player_release(null, $row['up_id'], auksjon::TYPE_KULER);
	
	// skal vi nedgradere v�penet?
	// man vil aldri miste v�pen, det blir alltid nedgradert til d�rligste v�pen
	// beholder resten av koden i tilfelle vi �nsker � gj�re forandringer igjen
	if ($row['up_weapon_id'] > 1)
	{
		$new_id = $row['up_weapon_id'] - 1;
		$new_w = &weapon::$weapons[$new_id];
		$training = weapon::DOWNGRADE_TRAINING;
		
		// sett til 50 % p� forrige v�pen
		ess::$b->db->query("
			UPDATE users_players
			SET up_weapon_id = $new_id, up_weapon_bullets = 0, up_weapon_training = $training
			WHERE up_id = {$row['up_id']} AND up_weapon_id = {$row['up_weapon_id']}");
		
		if (ess::$b->db->affected_rows() > 0)
		{
			// gi hendelse
			player::add_log_static("weapon_lost", $row['up_weapon_id'].":".urlencode($w['name']).":".urlencode($row['up_weapon_bullets']).":".urlencode($new_w['name']).":".$training, 1, $row['up_id']);
			
			// logg
			putlog("LOG", "NEDGRADERT V�PEN: {$row['up_name']} mistet v�penet {$w['name']} med {$row['up_weapon_bullets']} kuler grunnet lav v�pentrening. Fikk i stedet v�penet {$new_w['name']}.");
		}
	}
	
	else
	{
		// gi hendelse
		player::add_log_static("weapon_lost", $row['up_weapon_id'].":".urlencode($w['name']).":".urlencode($row['up_weapon_bullets']), 0, $row['up_id']);
		
		// logg
		putlog("LOG", "MISTET V�PEN: {$row['up_name']} mistet v�penet {$w['name']} med {$row['up_weapon_bullets']} kuler grunnet lav v�pentrening.");
	}
}

unset($w);

if (mysql_num_rows($result) > 0)
{
	// fjern v�pnene fra de som skal miste det
	ess::$b->db->query("
		UPDATE users_players
		SET up_weapon_id = NULL, up_weapon_bullets = 0
		WHERE up_weapon_training < 0.25 AND up_weapon_id > 1 AND up_access_level != 0");
}