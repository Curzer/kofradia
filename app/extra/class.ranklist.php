<?php

class ranklist
{
	/**
	 * Flush rank lista
	 */
	public static function flush()
	{
		// slett gamle lista
		ess::$b->db->query("TRUNCATE users_players_rank");
		
		// overfør spillerdata
		ess::$b->db->query("
			INSERT IGNORE INTO users_players_rank (upr_up_id, upr_up_access_level, upr_up_points)
			SELECT up_id, up_access_level, up_points
			FROM users_players");
		
		// oppdater lista med korrekte plasseringer
		self::update();
	}
	
	/**
	 * Oppdater ranklista
	 */
	public static function update()
	{
		ess::$b->db->query("SET @num = 1, @rank = 0, @p := NULL, @nc := NULL");
		ess::$b->db->query("
			UPDATE users_players_rank m, (
				SELECT
					upr_up_id,
					@nc := upr_up_access_level >= ".ess::$g['access_noplay']." OR upr_up_access_level = 0,
					@rank := IF(@rank = 0 OR @p > upr_up_points, @num, @rank) AS new_rank_pos,
					@num := IF(@nc, @num, @num + 1),
					@p := IF(@nc, @p, upr_up_points)
				FROM users_players_rank
				ORDER BY upr_up_points DESC
			) r
			SET m.upr_rank_pos = r.new_rank_pos
			WHERE m.upr_up_id = r.upr_up_id");
	}
}