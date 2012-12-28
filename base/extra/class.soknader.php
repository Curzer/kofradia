<?php

// prefiks i database: ds_ (Div. S�knader)

class soknader
{
	/** Type: Navnbytte for FF */
	const TYPE_FF_NAME = 1;
	
	/** Type s�knader */
	public static $types = array(
		1 => array(
			"name" => "ff_name",
			"title" => "Navnbytte for FF",
			"access" => "mod"
		)
	);
	
	/**
	 * Hente brukerID
	 */
	private static function up_id()
	{
		if (!login::$logged_in)
		{
			throw new HSException("Mangler brukerinformasjon.");
		}
		
		return login::$user->player->id;
	}
	
	/**
	 * Hent en bestemt type
	 * @param integer $type
	 */
	public static function get_type($type)
	{
		$type = (int) $type;
		if (!isset(self::$types[$type]))
		{
			return array(
				"name" => "ukjent",
				"title" => "Ukjent type ($type)",
				"access" => "admin"
			);
		}
		
		return self::$types[$type];
	}
	
	/**
	 * Legg til s�knad
	 */
	public static function add($type, $params, $reason, $rel_id = NULL)
	{
		global $_base, $__server;
		
		$type = (int) $type;
		$rel_id = $rel_id === NULL ? 'NULL' : intval($rel_id);
		
		// kontroller typen
		if (!isset(self::$types[$type]))
		{
			throw new HSException("Fant ikke typen.");
		}
		
		// legg til
		$_base->db->query("INSERT INTO div_soknader SET ds_type = $type, ds_up_id = ".self::up_id().", ds_rel_id = $rel_id, ds_time = ".time().", ds_reason = ".$_base->db->quote($reason).", ds_params = ".$_base->db->quote(serialize($params)));
		
		// oppdater cache
		tasks::set("soknader", mysql_result($_base->db->query("SELECT COUNT(ds_id) FROM div_soknader WHERE ds_reply_decision = 0"), 0));
		
		// logg
		putlog("NOTICE", "%bNY S�KNAD:%b {$__server['https_path']}{$__server['relative_path']}/crew/soknader");
		
		return $_base->db->insert_id();
	}
	
	/**
	 * Hent info om en s�knad
	 */
	public static function get($ds_id)
	{
		$ds_id = (int) $ds_id;
		
		global $_base;
		$result = $_base->db->query("SELECT ds_id, ds_type, ds_up_id, ds_rel_id, ds_time, ds_reason, ds_params, ds_reply_decision, ds_reply_reason, ds_reply_up_id, ds_reply_time FROM div_soknader WHERE ds_id = $ds_id");
		
		return mysql_fetch_assoc($result);
	}
	
	/**
	 * Hent informasjon om en s�knad
	 * @param string $type_name
	 * @param array $soknad
	 * @param mixed $params
	 * @return string error | array(bb => text, html => text, ..custom..)
	 */
	public static function get_info($type_name, $soknad, $params)
	{
		global $_base, $__server;
		switch ($type_name)
		{
			case "ff_name":
				// hent ffinfo
				$result = $_base->db->query("SELECT ff_id, ff_name FROM ff WHERE ff_id = {$soknad['ds_rel_id']}");
				
				// finnes ikke FF?
				$ff = mysql_fetch_assoc($result);
				if (!$ff)
				{
					return "Fant ikke FF.";
				}
				
				// navnet f�r og etter
				$name = $ff['ff_name'];
				if ($soknad['ds_reply_decision'] == 1 && $params['name_old'] != $ff['ff_name'])
				{
					$name = $params['name_old'].' ('.$name.')';
				}
				
				// sett opp beskrivelse
				return array(
					"bb" => 'Bytte navn p� FF '.$name.' til [b]'.$params['name'].'[/b] ([iurl=/ff/?ff_id='.$ff['ff_id'].']vis FF[/iurl]).',
					"html" => 'Bytte navn p� FF '.htmlspecialchars($name).' til <b>'.htmlspecialchars($params['name']).'</b> (<a href="'.$__server['relative_path'].'/ff/?ff_id='.$ff['ff_id'].'">vis FF</a>).',
					"ff_name" => $ff['ff_name']
				);
		}
		
		throw new HSException("Ukjent type.");
	}
	
	/**
	 * Godta/avsl� s�knad
	 * @param boolean $outcome - om s�knaden blir innvilget eller ikke
	 * @param integer $ds_id
	 * @param string $reason
	 */
	public static function decide($outcome, $ds_id, $reason)
	{
		global $_base, $__server;
		$ds_id = (int) $ds_id;
		
		// hent s�knaden
		$soknad = self::get($ds_id);
		
		// fant ikke s�knaden?
		if (!$soknad)
		{
			return false;
		}
		
		// er s�knaden allerede behandlet?
		if ($soknad['ds_reply_decision'] != 0)
		{
			return false;
		}
		
		// typen
		$type = self::get_type($soknad['ds_type']);
		
		// har vi tilgang til s�knaden?
		if (!access::has($type['access']))
		{
			return false;
		}
		
		// sjekk for tom begrunnelse
		$have_reason = trim(game::format_data($reason)) != "";
		
		// sett opp params
		$params = unserialize($soknad['ds_params']);
		
		// sett opp s�knadsinfo
		$info = self::get_info($type['name'], $soknad, $params);
		
		// info er ikke gyldig - s�knaden er ikke gyldig
		if (!is_array($info))
		{
			// slett s�knaden
			self::delete($ds_id);
			
			return $info;
		}
		
		// avsl� s�knad
		if (!$outcome)
		{
			$msg = 'bb:'.$type['title'].': Din s�knad ble avsl�tt. ('.$info['bb'].') Begrunnelse: '.($have_reason ? $reason : 'Ingen begrunnelse gitt.');
			
			// spesielle handlinger
			switch ($type['name'])
			{
				case "ff_name":
					// sett tilbakepengene p� bankkontoen
					if (isset($params['cost']) && $params['cost'] > 0)
					{
						$msg .= ' Bel�pet p� '.game::format_cash($params['cost']).' som ble innbetalt ved s�knad er satt inn p� kontoen igjen.';
						ff::bank_static(ff::BANK_TILBAKEBETALING, $params['cost'], $soknad['ds_rel_id'], 'Navns�knad avsl�tt: '.$params['name']);
					}
				break;
			}
		}
		
		// innvilge
		else
		{
			$msg = 'bb:'.$type['title'].': Din s�knad har blitt innvilget. ('.$info['bb'].') Begrunnelse: '.($have_reason ? $reason : 'Ingen begrunnelse gitt.');
			
			// spesielle handlinger
			switch ($type['name'])
			{
				case "ff_name":
					$ff = ff::get_ff($soknad['ds_rel_id'], ff::LOAD_SCRIPT);
					if ($ff)
					{
						$ff->change_name($params['name'], $soknad['ds_up_id']);
						
						// lagre gammelt navn p� FF i s�knaden
						$params['name_old'] = $info['ff_name'];
					}
				break;
			}
		}
		
		// legg til logg hos spilleren
		player::add_log_static("soknader", $msg, 0, $soknad['ds_up_id']);
		
		// oppdater s�knaden
		$_base->db->query("UPDATE div_soknader SET ds_params = ".$_base->db->quote(serialize($params)).", ds_reply_decision = ".($outcome ? 1 : -1).", ds_reply_reason = ".$_base->db->quote($reason).", ds_reply_up_id = ".self::up_id().", ds_reply_time = ".time()." WHERE ds_id = $ds_id");
		
		// oppdater cache
		tasks::set("soknader", mysql_result($_base->db->query("SELECT COUNT(ds_id) FROM div_soknader WHERE ds_reply_decision = 0"), 0));
		
		return $info;
	}
	
	/**
	 * Slett s�knad
	 * @param integer $ds_id
	 * @param boolean $force slette selv om den er behandlet
	 */
	public static function delete($ds_id, $force = false)
	{
		global $_base;
		$ds_id = (int) $ds_id;
		
		$where = $force ? '' : ' AND ds_reply_decision = 0';
		
		// slett s�knaden
		$_base->db->query("DELETE FROM div_soknader WHERE ds_id = $ds_id$where");
		
		if ($_base->db->affected_rows() == 0) return false;
		
		// oppdater cache
		tasks::set("soknader", mysql_result($_base->db->query("SELECT COUNT(ds_id) FROM div_soknader WHERE ds_reply_decision = 0"), 0));
		return true;
	}
}