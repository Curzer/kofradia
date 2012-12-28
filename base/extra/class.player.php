<?php

/**
 * Spillersystemet
 */
class player
{
	/**
	 * Helse under denne verdien f�rer til at man mister medlemskap i familier/firma
	 */
	const FF_HEALTH_LOW = 0.4;
	
	/**
	 * Hvor lav helse vi m� ha for at vi automatisk blir flyttet til en annen bydel
	 */
	const HEALTH_MOVE_AUTO = 0.4; // samme som n�r man mister FF
	
	/** Samling av spillerobjekter */
	protected static $players = array();
	
	/** ID for spilleren */
	public $id;
	
	/** Spillerdata */
	public $data;
	
	/** Levende? */
	public $active;
	
	/**
	 * Brukerobjektet til spilleren
	 * @var user
	 */
	public $user;
	
	/**
	 * Params
	 * @var params_update
	 */
	public $params;
	
	/** Rankinformasjon */
	public $rank;
	
	/** Bydelsinformasjon */
	public $bydel;
	
	/**
	 * Oppdrag
	 * @var oppdrag
	 */
	public $oppdrag;
	
	/**
	 * V�pen
	 * @var weapon
	 */
	public $weapon;
	
	/**
	 * Beskyttelse
	 * @var protection
	 */
	public $protection;
	
	/**
	 * Prestasjoner
	 * @var achievements_player
	 */
	public $achievements;
	
	/**
	 * Hent spillerobjekt
	 * @param integer $up_id
	 * @param user $user_object brukerobjektet som denne spilleren skal v�re standard spiller for
	 * @param boolean $find_user sett til true hvis $up_id er spillernavnet
	 * @return player
	 */
	public static function get($up_id, user $user_object = NULL, $find_user = NULL)
	{
		// allerede lastet inn?
		if (isset(self::$players[$up_id]))
		{
			$player = self::$players[$up_id];
			if ($user_object && !isset($player->user)) $player->user = $user_object;
			return $player;
		}
		
		$player = new player($up_id, $user_object, $find_user);
		if (!$player->data) return false;
		
		// lagre objektet for evt. senere bruk
		self::$players[$player->id] = $player;
		
		return $player;
	}
	
	/**
	 * Hent en spiller dersom den allerede er lastet inn
	 */
	public static function get_loaded($up_id)
	{
		if (isset(self::$players[$up_id]))
		{
			return self::$players[$up_id];
		}
		
		return false;
	}
	
	/**
	 * Last inn spillerinformasjon
	 * @param integer $up_id
	 * @param user $user_object brukerobjektet som denne spilleren skal v�re standard spiller for
	 * @param boolean $find_user sett til true hvis $up_id er spillernavnet
	 */
	public function __construct($up_id, user $user_object = NULL, $find_user = NULL)
	{
		// hent informasjon
		$where = !$find_user ? "up_id = " . intval($up_id) : "up_name = ".ess::$b->db->quote($up_id);
		$order = $find_user ? " ORDER BY up_access_level = 0, up_last_online DESC" : "";
		
		$this->load_data(false, $where, $order);
		
		return $this->process_data($user_object, true);
	}
	
	/**
	 * Prosesser spillerdata
	 */
	protected function process_data(user $user_object = NULL, $is_login = NULL)
	{
		// fant ikke spilleren?
		if (!$this->data)
		{
			return false;
		}
		
		// sett opp id og info
		$this->id = $this->data['up_id'];
		$this->active = $this->data['up_access_level'] != 0;
		
		// fjern variablene som skal lastes n�r de blir benyttet
		$this->__wakeup(true);
		
		// referanse fra og til brukerobjekt?
		if ($user_object)
		{
			$this->user = $user_object;
			if ($is_login) $user_object->player = $this;
		}
		
		// m� ranklista oppdateres?
		if ($this->data['upr_rank_pos'] === null)
		{
			ranklist::flush();
			
			// hent oppdatert plassering
			$result = ess::$b->db->query("SELECT upr_rank_pos FROM users_players_rank WHERE upr_up_id = $this->id");
			$row = mysql_fetch_assoc($result);
			if ($row) $this->data['upr_rank_pos'] = $row['upr_rank_pos'];
		}
		
		// sjekke spesifikk spillerinfo/status? (kun hvis dette er den aktive brukeren og ikke ajax kall)
		if (login::is_active_user($this) && !defined("SCRIPT_AJAX"))
		{
			// oppdrag
			$this->__get("oppdrag");
			
			// fengsel
			$this->fengsel_dusor_check();
		}
	}
	
	/**
	 * L�s brukeren og hent fersk data
	 */
	public function lock()
	{
		ess::$b->db->begin();
		$this->load_data(true);
		$this->process_data($this->user);
	}
	
	/**
	 * Hent inn spillerdata
	 */
	protected function load_data($lock = NULL, $where = NULL, $order = NULL)
	{
		global $_game;
		
		if (!$where)
		{
			$where = "up_id = $this->id";
			$groupby = "";
		}
		else
		{
			$groupby = " GROUP BY up_id";
		}
		if (!$order) $order = "";
		
		$lock = $lock ? " FOR UPDATE" : "";
		
		$result = ess::$b->db->query("
			SELECT
				users_players.*,
				upr_rank_pos
			FROM users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE $where$groupby$order
			LIMIT 1$lock");
		
		$this->data = mysql_fetch_assoc($result);
	}
	
	/**
	 * Fiks objektet hvis det har v�rt serialized
	 */
	public function __wakeup($clean = NULL)
	{
		// slett objektene p� nytt hvis de ikke er initialisert med __get
		if (!isset($this->params) || $clean) unset($this->params);
		if (!isset($this->oppdrag) || $clean) unset($this->oppdrag);
		if (!isset($this->rank) || $clean) unset($this->rank);
		if (!isset($this->user) || $clean) unset($this->user);
		if (!isset($this->bydel) || $clean) unset($this->bydel);
		if (!isset($this->weapon) || $clean) unset($this->weapon);
		if (!isset($this->protection) || $clean) unset($this->protection);
		if (!isset($this->achievements) || $clean) unset($this->achievements);
	}
	
	/**
	 * Last inn objekter f�rst n�r de skal benyttes
	 */
	public function __get($name)
	{
		switch ($name)
		{
			// oppdrag
			case "oppdrag":
				$this->oppdrag = true;
				new oppdrag($this, null, $this->oppdrag);
				return $this->oppdrag;
			break;
			
			// params
			case "params":
				$this->params = new params_update($this->data['up_params'], "users_players", "up_params", "up_id = $this->id");
				return $this->params;
			break;
			
			// rank
			case "rank":
				$this->rank = game::rank_info($this->data['up_points'], $this->data['upr_rank_pos'], $this->data['up_access_level']);
				return $this->rank;
			break;
			
			// bruker
			case "user":
				// er dette brukeren som er logget inn?
				if (login::$logged_in && login::$user->id == $this->data['up_u_id']) $this->user = login::$user;
				else $this->user = new user($this->data['up_u_id']);
				return $this->user;
			break;
			
			// bydel
			case "bydel":
				// sett opp informasjon om bydel
				$this->bydel = &game::$bydeler[$this->data['up_b_id']];
				return $this->bydel;
			break;
			
			// v�pen
			case "weapon":
				$this->weapon = $this->data['up_weapon_id'] != 0 ? weapon::get($this->data['up_weapon_id'], $this) : false;
				return $this->weapon;
			break;
			
			// beskyttelse
			case "protection":
				$this->protection = protection::get($this->data['up_protection_id'], $this->data['up_protection_state'], $this);
				return $this->protection;
			break;
			
			// prestasjoner
			case "achievements":
				essentials::load_module("achievements");
				$this->achievements = new achievements_player($this);
				return $this->achievements;
			break;
		}
	}
	
	/**
	 * Send melding
	 * @param integer/array $to_up_id
	 * @param string $title
	 * @param string $text
	 * @param boolean $outbox
	 */
	public function send_message($to_up_id, $title, $text, $outbox = true)
	{
		// sett opp liste over mottakere
		$up_list = array();
		if (is_array($to_up_id))
		{
			foreach ($to_up_id as $row)
			{
				// tillatt � send med arrays som har array med up_id felt
				if (isset($row['up_id'])) $row = (int) $row['up_id'];
				else $row = (int) $row;
				
				if ($row <= 0) throw new HSException("Ugyldig mottaker: $row");
				$up_list[] = $row;
			}
			$up_list = array_unique($up_list);
			if (count($up_list) == 0) throw new HSException("Ingen mottakere.");
		}
		else
		{
			$val = (int) $to_up_id;
			if ($val <= 0) throw new HSException("Ugyldig mottaker.");
			
			$up_list[] = $val;
		}
		
		// opprett thread
		$time = time();
		ess::$b->db->query("INSERT INTO inbox_threads SET it_title = ".ess::$b->db->quote($title));
		$it_id = ess::$b->db->insert_id();
		
		// opprett melding
		ess::$b->db->query("INSERT INTO inbox_messages SET im_it_id = $it_id, im_up_id = $this->id, im_time = $time");
		$im_id = ess::$b->db->insert_id();
		
		// opprett data
		ess::$b->db->query("INSERT INTO inbox_data SET id_im_id = $im_id, id_text = ".ess::$b->db->quote($text));
		
		// sett opp relasjoner
		$to_add = array();
		$id_list = array();
		$in_list = false;
		foreach ($up_list as $row)
		{
			$to_add[] = "($it_id, $row, 1, 0, $time)";
			if ($row == $this->id) $in_list = true;
			else $id_list[] = $row;
		}
		if (!$in_list)
		{
			$to_add[] = "($it_id, {$this->id}, 0, ".($outbox ? 0 : 1).", $time)";
		}
		
		// opprett relasjoner
		ess::$b->db->query("INSERT INTO inbox_rel (ir_it_id, ir_up_id, ir_unread, ir_deleted, ir_restrict_im_time) VALUES ".implode(",", $to_add));
		
		// oppdater egen brukerinfo
		ess::$b->db->query("
			UPDATE users, users_players
			SET
				up_inbox_num_threads = up_inbox_num_threads + 1,
				u_inbox_sent_time = $time
			WHERE u_id = ".$this->user->id."
			  AND up_id = ".$this->id);
		
		// oppdater brukere
		if (count($id_list) == 0) $id_list[] = $to_up_id; // fiks for at det alltid vil v�re en spiller som f�r ny melding
		ess::$b->db->query("
			UPDATE users, users_players
			SET u_inbox_new = u_inbox_new + 1
			WHERE up_id IN (".implode(",", $id_list).") AND up_u_id = u_id");
		
		// logg
		putlog("LOG", "%c13%bMELDING%b%c: %u".$this->data['up_name']."%u opprettet ny meldingstr�d med it_id {$it_id} (%u{$title}%u). Lengde: ".strlen($text)." bytes! ".ess::$s['path']."/innboks_les?id={$it_id}");
		
		return $it_id;
	}
	
	/**
	 * Endre rankpoengene for spilleren
	 * @param integer $points_change
	 * @param boolean $use_login skal vi oppdatere sesjonsinfo hvis dette er den innloggede spilleren?
	 * @param boolean $silent ikke annonser svaret p� f.eks. IRC
	 * @param integer $points_change_rel
	 * @param string $oppdrag_name navn for oppdragtrigger � identifisere funksjonen som gav poeng
	 */
	public function increase_rank($points_change, $use_login = true, $silent = null, $points_change_rel = null, $oppdrag_name = null)
	{
		return self::increase_rank_static($points_change, $this, $use_login, $silent, $points_change_rel, $oppdrag_name);
	}
	
	/**
	 * Endre rankpoengene for en bestemt spiller
	 * @param integer $points_change
	 * @param player $up (evt. integer)
	 * @param boolean $use_login skal vi oppdatere sesjonsinfo hvis dette er den innloggede spilleren?
	 * @param boolean $silent ikke annonser svaret p� f.eks. IRC
	 * @param integer $points_change_rel
	 * @param string $oppdrag_name navn for oppdragtrigger � identifisere funksjonen som gav poeng
	 * @return integer rank pos change/boolean false 404
	 */
	public static function increase_rank_static($points_change, $up, $use_login = false, $silent = null, $points_change_rel = null, $oppdrag_name = null)
	{
		// hent ut spillerid
		if (!is_numeric($up) && (!is_object($up) || !($up instanceof player))) throw new HSException("Ukjent spiller.");
		if (is_numeric($up))
		{
			$up_id = $up;
			$up = player::get($up_id);
		}
		else $up_id = $up->id;
		
		// tilh�rer spilleren brukeren som er logget inn?
		$is_login = $use_login && login::$logged_in && $up_id == login::$user->player->id;
		
		// hent helt fersk spillerinfo
		$result = ess::$b->db->query("
			SELECT up_name, up_access_level, up_points, upr_rank_pos
			FROM users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE up_id = $up_id");
		$row = mysql_fetch_assoc($result);
		
		// sett opp info
		$points_change_rel = $points_change_rel === null ? $points_change : (int) $points_change_rel;
		$access_level = $row['up_access_level'];
		$points = $row['up_points'];
		$points_after = $points + $points_change;
		$points_after_rel = $points + $points_change_rel;
		$rank_pos = $row['upr_rank_pos'];
		$name = $row['up_name'];
		$pos_change = 0;
		$extra = "";
		
		// m� ranklista oppdateres?
		if ($rank_pos === null)
		{
			ranklist::flush();
			
			// hent oppdatert plassering
			$result = ess::$b->db->query("SELECT upr_rank_pos FROM users_players_rank WHERE upr_up_id = $up_id");
			$row = mysql_fetch_assoc($result);
			
			// har fortsatt ikke plassering?
			if (!$row)
			{
				throw new HSException("Klarer ikke � finne korrekt rankplassering.");
			}
		}
		
		// ranken vi har n�
		$rank = game::rank_info($points);
		$rank_num_now = $rank['number'];
		
		// ranken vi kommer til � v�re p� etter endring
		$rank_after = game::rank_info($points_after);
		$rank_num_after = $rank_after['number'];
		
		$invisible = $access_level >= ess::$g['access_noplay'] || $access_level == 0;
		
		// positiv forandring
		if ($points_change > 0)
		{
			// hent ny rankplassering
			$result = ess::$b->db->query("
				SELECT MIN(upr_rank_pos)
				FROM users_players_rank
				WHERE upr_up_points > $points AND upr_up_points <= $points_after AND upr_up_access_level != 0 AND upr_up_access_level < ".ess::$g['access_noplay']);
			
			// endre rankplassering?
			$pos = mysql_result($result, 0);
			if ($pos !== NULL)
			{
				#$extra = ", up_rank_pos = $pos";
				$pos_change = $rank_pos - $pos;
				if ($up) $up->data['upr_rank_pos'] -= $pos_change;
			}
			
			// oppdater brukeren
			ess::$b->db->query("UPDATE users_players SET up_points = up_points + $points_change, up_points_rel = up_points_rel + $points_change_rel$extra WHERE up_id = $up_id");
			
			// oppdater ranklisten
			ess::$b->db->query("UPDATE users_players_rank SET upr_up_points = upr_up_points + $points_change WHERE upr_up_id = $up_id");
			
			// oppdater rankplasseringen til de vi g�r forbi
			#if (!$invisible) ess::$b->db->query("UPDATE users_players SET up_rank_pos = up_rank_pos + 1 WHERE up_points >= $points AND up_points < $points_after");
			ranklist::update();
		}
		
		// negativ forandring
		elseif ($points_change < 0)
		{
			// hent ny rankplassering
			$result = ess::$b->db->query("
				SELECT MAX(upr_rank_pos)
				FROM users_players_rank
				WHERE upr_up_points < $points AND upr_up_points > $points_after AND upr_up_access_level != 0 AND upr_up_access_level < ".ess::$g['access_noplay']);
			
			// endre rankplassering?
			$pos = mysql_result($result, 0);
			if ($pos !== NULL)
			{
				$pos++;
				#$extra = ", up_rank_pos = $pos";
				$pos_change = $rank_pos - $pos;
				if ($up) $up->data['upr_rank_pos'] -= $pos_change;
			}
			
			// oppdater brukeren
			ess::$b->db->query("UPDATE users_players SET up_points = up_points + $points_change, up_points_rel = up_points_rel + $points_change_rel$extra WHERE up_id = $up_id");
			
			// oppdater ranklisten
			ess::$b->db->query("UPDATE users_players_rank SET upr_up_points = upr_up_points + $points_change WHERE upr_up_id = $up_id");
			
			// oppdater rankplasseringen til de vi g�r forbi
			#if (!$invisible) ess::$b->db->query("UPDATE users_players SET up_rank_pos = GREATEST(1, up_rank_pos - 1) WHERE up_points < $points AND up_points >= $points_after");
			ranklist::update();
		}
		
		// oppdater info knyttet til users_hits og sessions
		if ($is_login)
		{
			ess::$b->db->query("UPDATE users_hits SET uhi_points = uhi_points + $points_change WHERE uhi_up_id = $up_id AND uhi_secs_hour = ".login::$info['secs_hour']);
			ess::$b->db->query("UPDATE sessions SET ses_points = ses_points + $points_change WHERE ses_id = ".login::$info['ses_id']);
		}
		
		// logg
		putlog("SPAMLOG", "%c6%bRANKPOENG:%b%c %u{$name}%u skaffet %b%u{$points_change}%u%b rankpoeng ({$_SERVER['REQUEST_URI']}) ({$_SERVER['REQUEST_METHOD']})");
		
		// �kning?
		if ($rank_num_after > $rank_num_now)
		{
			for ($i = $rank_num_now+1; $i <= $rank_num_after && $i <= count(game::$ranks['items']); $i++)
			{
				$rank_name = game::$ranks['items_number'][$i]['name'];
				
				// forfremmet
				self::add_log_static("forfremmelse", $rank_name, 0, $up_id);
				
				// mirc msg
				if (!$silent)
				{
					// live-feed
					if ($i >= 6) livefeed::add_row('<user id="'.$up_id.'" /> ble forfremmet til '.htmlspecialchars($rank_name).'.');
					
					$type = $i >= 6 ? "INFO" : "SPAM";
					putlog($type, "%bFORFREMMELSE:%b %u{$name}%u ble forfremmet til %b$rank_name%b!");
				}
			}
		}
		
		// nedgang?
		elseif ($rank_num_after < $rank_num_now)
		{
			for ($i = $rank_num_now-1; $i >= $rank_num_after && $i > 0; $i--)
			{
				$rank_name = game::$ranks['items_number'][$i]['name'];
				
				// nedgradert
				self::add_log_static("nedgradering", $rank_name, 0, $up_id);
				
				// mirc msg
				if (!$silent)
				{
					// live-feed
					if ($i >= 6) livefeed::add_row('<user id="'.$up_id.'" /> ble nedgradert til '.htmlspecialchars($rank_name).'.');
					
					putlog("INFO", "%bNEDGRADERING:%b %u{$name}%u ble nedgradert til %b$rank_name%b!");
				}
			}
		}
		
		// sett ny helse og energi
		if ($rank_num_after != $rank_num_now)
		{
			ess::$b->db->query("
				UPDATE users_players
				SET
					up_health = ROUND(up_health * ({$rank_after['rank_max_health']} / up_health_max)), up_health_max = {$rank_after['rank_max_health']},
					up_energy = ROUND(up_energy * ({$rank_after['rank_max_energy']} / up_energy_max)), up_energy_max = {$rank_after['rank_max_energy']}
				WHERE up_id = $up_id");
		}
		
		// sjekke oppdrag?
		
		// kontroller spiller
		if ($up)
		{
			// nullstill rankobjekt
			unset($up->rank);
			
			// sett ny helse og energi
			if ($rank_num_after != $rank_num_now)
			{
				$up->data['up_health'] = round($up->data['up_health'] * ($rank_after['rank_max_health'] / $up->data['up_health_max']));
				$up->data['up_health_max'] = $rank_after['rank_max_health'];
				$up->data['up_energy'] = round($up->data['up_energy'] * ($rank_after['rank_max_energy'] / $up->data['up_energy_max']));
				$up->data['up_energy_max'] = $rank_after['rank_max_energy'];
			}
			
			// oppdater rankpoeng
			$up->data['up_points'] += $points_change;
			$up->data['up_points_rel'] += $points_change_rel;
			
			// fyr av trigger
			$up->trigger("rank_points", array(
				"source" => $oppdrag_name,
				"points" => $points_change,
				"points_rel" => $points_change_rel,
				"points_after" => $points_after,
				"points_after_rel" => $points_after_rel,
				"rank" => $rank_num_after-$rank_num_now,
				"pos" => $pos_change));
		}
		
		// har vi endret plass n�r vi er i top 100 lista?
		if (($rank_pos <= 10 || $pos <= 10) && $pos_change != 0 && !$silent)
		{
			if ($pos_change < 0)
			{
				putlog("INFO", "%bRANKNUMMER:%b %u{$name}%u falt tilbake %b".abs($pos_change)."%b plass".($pos_change != -1 ? 'er' : '')." til %b$pos.%b plass..");
				
				// live-feed
				livefeed::add_row('<user id="'.$up_id.'" /> falt tilbake <b>'.abs($pos_change).'</b> plass'.($pos_change != -1 ? 'er' : '').' til <b>'.$pos.'</b> plass.');
			}
			else
			{
				putlog("INFO", "%bRANKNUMMER:%b %u{$name}%u avanserte %b".$pos_change."%b plass".($pos_change != 1 ? 'er' : '')." til %b$pos.%b plass!");
				
				// live-feed
				livefeed::add_row('<user id="'.$up_id.'" /> avanserte <b>'.abs($pos_change).'</b> plass'.($pos_change != 1 ? 'er' : '').' til <b>'.$pos.'</b>. plass.');
			}
		}
		
		return $pos_change;
	}
	
	/**
	 * Legg til melding i hendelser
	 * @param integer/string $type
	 * @param string $message
	 * @param integer $num
	 */
	public function add_log($type, $message, $num = 0)
	{
		return self::add_log_static($type, $message, $num, $this->id);
	}
	
	/**
	 * Legg til melding i hendelser for en spiller
	 * @param integer/string $type
	 * @param string $message
	 * @param integer $num
	 * @param integer $up_id
	 */
	public static function add_log_static($type, $message, $num, $up_id)
	{
		// hente inn korrekt type?
		if (!is_numeric($type))
		{
			// finnes ikke?
			if (!isset(gamelog::$items[$type])) throw new HSException("Ugyldig type for logg.");
			
			$type = gamelog::$items[$type];
		}
		
		$type = (int) $type;
		$message = ess::$b->db->quote($message);
		$num = game::intval($num);
		$up_id = (int) $up_id;
		
		// legg til melding og �k telleren til brukeren
		ess::$b->db->query("INSERT INTO users_log (time, ul_up_id, type, note, num) VALUES (".time().", $up_id, $type, $message, $num)");
		$id = ess::$b->db->insert_id();
		
		ess::$b->db->query("UPDATE users_players SET up_log_new = up_log_new + 1 WHERE up_id = $up_id");
		
		if (login::$logged_in && login::$user->player->id == $up_id)
		{
			login::$user->player->data['up_log_new']++;
		}
		
		return $id;
	}
	
	/**
	 * Returner profillenke
	 */
	public function profile_link($link = true, $linkurl = NULL)
	{
		return game::profile_link($this->id, $this->data['up_name'], $this->data['up_access_level'], $link, $linkurl);
	}
	
	/**
	 * Status for kriminalitet
	 */
	public function status_kriminalitet()
	{
		$info = array(
			"last" => false,
			"wait_time" => 0
		);
		$result = ess::$b->db->query("SELECT s.last, k.wait_time FROM kriminalitet_status AS s, kriminalitet AS k WHERE ks_up_id = $this->id AND s.krimid = k.id ORDER BY s.last DESC LIMIT 1");
		$row = mysql_fetch_assoc($result);
		mysql_free_result($result);
		if ($row)
		{
			$info['last'] = $row['last'];
			$info['wait_time'] = max(0, $row['last'] + $row['wait_time'] - time());
		}
		return $info;
	}
	
	/**
	 * Status for utpressing
	 */
	public function status_utpressing()
	{
		return array(
			"wait_time" => max(0, $this->data['up_utpressing_last'] + utpressing::DELAY_TIME - time())
		);
	}
	
	/**
	 * Status for GTA
	 */
	public function status_gta()
	{
		$info = array(
			"last" => false,
			"wait_time" => 0
		);
		$result = ess::$b->db->query("SELECT MAX(time_last) last FROM gta_options_status WHERE gos_up_id = $this->id");
		$row = mysql_fetch_assoc($result);
		mysql_free_result($result);
		if ($row)
		{
			$info['last'] = $row['last'];
			$info['wait_time'] = max(0, $row['last'] + game::$settings['delay_biltyveri']['value'] - time());
		}
		return $info;
	}
	
	/**
	 * Status for lotto
	 */
	public function status_lotto()
	{
		$info = array(
			"time_wait" => 0
		);
		
		// finn ut om vi er i en aktiv periode (kan kj�pe lodd)
		$date = ess::$b->date->get();
		$lotto_active = ($date->format("i")/2 % 15) != 7;
		
		// finn n�r neste trekning er
		$offset_now = $date->format("i")*60+$date->format("s");
		$lotto_next = ($offset_now >= 2700 ? 4500-$offset_now : ($offset_now >= 900 ? 2700-$offset_now : 900-$offset_now));
		
		// ventetiden f�r vi sjekker om vi har lodd
		$info['wait_time'] = $lotto_active ? 0 : ($lotto_next <= 60 ? $lotto_next + 60 : $lotto_next-1740);
		
		// har vi noen lodd n�?
		$result = ess::$b->db->query("SELECT MAX(time) FROM lotto WHERE l_up_id = $this->id");
		$next = mysql_result($result, 0);
		mysql_free_result($result);
		if ($next)
		{
			$info['wait_time'] = max($info['wait_time'], $next - time() + lotto::$ventetid);
		}
		
		return $info;
	}
	
	/**
	 * Aktiver spilleren
	 */
	public function activate()
	{
		global $_game, $__server;
		
		// er aktivert?
		if ($this->active) return false;
		
		$this->active = true;
		$this->data['up_access_level'] = 1;
		
		// aktiver spilleren og sett helse og energi til maks
		ess::$b->db->query("UPDATE users_players SET up_access_level = 1, up_health = up_health_max, up_energy = up_energy_max WHERE up_id = $this->id AND up_access_level = 0");
		if (ess::$b->db->affected_rows() == 0) return false;
		
		// oppdater ranklisten
		/*ess::$b->db->query("
			UPDATE users_players, (SELECT up_id ref_up_id FROM users_players WHERE up_points = {$this->data['up_points']} AND up_id != $this->id AND up_access_level < {$_game['access_noplay']} LIMIT 1) ref
			SET up_rank_pos = up_rank_pos + 1 WHERE ref_up_id IS NULL AND up_points < {$this->data['up_points']}");*/
		ess::$b->db->query("UPDATE users_players_rank SET upr_up_access_level = 1 WHERE upr_up_id = $this->id");
		ranklist::update();
		
		// fjern tilknytninger til FF
		ff::set_leave($this->id);
		
		putlog("CREWCHAN", "%bAktivering%b: Spilleren {$this->data['up_name']} er n� aktivert igjen ".$this->generate_minside_url());
		return true;
	}
	
	/**
	 * Deaktiver spilleren
	 */
	public function deactivate($reason, $note, player $by_up)
	{
		global $__server;
		
		// er ikke aktivert?
		if (!$this->active) return false;
		
		$prev_level = $this->data['up_access_level'];
		$this->active = false;
		$this->data['up_access_level'] = 0;
		$this->data['up_deactivated_time'] = time();
		$this->data['up_deactivated_up_id'] = $by_up->id;
		$this->data['up_deactivated_dead'] = 0;
		$this->data['up_deactivated_reason'] = empty($reason) ? NULL : $reason;
		$this->data['up_deactivated_note'] = empty($note) ? NULL : $note;
		$this->data['up_deactivated_points'] = $this->data['up_points'];
		$this->data['up_deactivated_rank_pos'] = $this->data['upr_rank_pos'];
		
		// deaktiver spilleren
		ess::$b->db->query("
			UPDATE users_players LEFT JOIN users_players_rank ON upr_up_id = up_id
			SET
				up_access_level = 0, up_deactivated_time = {$this->data['up_deactivated_time']}, up_deactivated_up_id = $by_up->id, up_deactivated_dead = 0,
				up_deactivated_reason = ".ess::$b->db->quote($reason).", up_deactivated_note = ".ess::$b->db->quote($note).",
				up_deactivated_points = up_points, up_deactivated_rank_pos = upr_rank_pos
			WHERE up_id = $this->id AND up_access_level != 0");
		if (ess::$b->db->affected_rows() == 0) return false;
		
		$ret = $this->release_relations($prev_level, $by_up, false);
		
		// deaktiverte seg selv?
		if ($by_up->id == $this->id)
		{
			// under 40 % helse?
			if ($this->data['up_health'] / $this->data['up_health_max'] < self::FF_HEALTH_LOW)
			{
				// gi kreditt etc til siste angriper
				$by_up = $this->bleed_handle();
				
				// trigger for angriper
				if ($by_up)
				{
					$by_up->trigger("attack_bleed", array(
							"res" => $ret,
							"up" => $player));
				}
			}
			
			$info = 'deaktiverte seg selv';
		}
		
		// ble deaktivert?
		else
		{
			$info = 'ble deaktivert';
			if (login::$logged_in) $info .= ' av '.login::$user->player->data['up_name'];
		}
		putlog("CREWCHAN", "%bDeaktivering%b: Spilleren {$this->data['up_name']} $info ".$this->generate_minside_url());
		
		// gi tilbake eventuelle oppf�ringer i hitlisten
		etterlyst::player_dies($this);
		
		return true;
	}
	
	/**
	 * Spiller blir drept
	 * @param bool $instant d�de spilleren momentant?
	 * @param player $by_up hvem som for�rsaket d�dsfallet
	 */
	public function dies($instant, player $by_up = NULL)
	{
		// er ikke aktivert?
		if (!$this->active) return false;
		
		$ret = array();
		if ($instant) $ret['penger_bank'] = 0;
		
		$prev_level = $this->data['up_access_level'];
		$this->active = false;
		$this->data['up_access_level'] = 0;
		$this->data['up_deactivated_time'] = time();
		$this->data['up_deactivated_up_id'] = $by_up ? $by_up->id : NULL;
		$this->data['up_deactivated_dead'] = $instant ? 1 : 2;
		$this->data['up_deactivated_reason'] = NULL;
		$this->data['up_deactivated_note'] = NULL;
		$this->data['up_deactivated_points'] = $this->data['up_points'];
		$this->data['up_deactivated_rank_pos'] = $this->data['upr_rank_pos'];
		
		// deaktiver spilleren
		$by_up_id = $by_up ? $by_up->id : 'NULL';
		ess::$b->db->query("
			UPDATE users_players LEFT JOIN users_players_rank ON upr_up_id = up_id
			SET
				up_access_level = 0, up_deactivated_time = {$this->data['up_deactivated_time']}, up_deactivated_up_id = $by_up_id, up_deactivated_dead = {$this->data['up_deactivated_dead']},
				up_deactivated_reason = NULL, up_deactivated_note = NULL,
				up_deactivated_points = up_points, up_deactivated_rank_pos = upr_rank_pos
			WHERE up_id = $this->id AND up_access_level != 0");
		if (ess::$b->db->affected_rows() == 0) return false;
		
		// har vi noen som skal f� penger vi har i banken?
		if ($this->data['up_bank'] > 0)
		{
			// hent liste over FF-id vi er med i
			$ff_list = $this->get_ff_id_list();
			$num_ff = count($ff_list);
			if ($num_ff > 0)
			{
				$ff_ids = implode(",", $ff_list);
				
				// familier som skal f� penger
				$result = ess::$b->db->query("
					SELECT ff_id
					FROM ff
					WHERE ff_id IN ($ff_ids) AND ff_type = 1 AND ff_is_crew = 0");
				$num_ff = mysql_num_rows($result);
			}
			
			// har vi noen familier eller ble drept instant
			if ($num_ff > 0 || $instant)
			{
				// hent ut bel�pet i banken og sett bankkontoen til 10 %
				ess::$b->db->query("
					UPDATE users_players, (SELECT up_id ref_up_id, @bank := up_bank FROM users_players WHERE up_id = $this->id) ref
					SET up_bank = up_bank * 0.1
					WHERE ref_up_id = up_id");
				$bank = mysql_result(ess::$b->db->query("SELECT @bank"), 0);
				
				if ($num_ff > 0 && $instant)
				{
					$f_ff = 0.3;
					$f_up = 0.6;
				}
				elseif ($num_ff > 0)
				{
					$f_ff = 0.9;
					$f_up = 0;
				}
				else
				{
					$f_ff = 0;
					$f_up = 0.9;
				}
				
				// noe til familie?
				if ($f_ff > 0)
				{
					// hvor mye hver familie skal f�
					$ff_bank_each = bcdiv(bcmul($bank, $f_ff), $num_ff);
					
					// del ut pengene til familiene
					while ($row = mysql_fetch_assoc($result))
					{
						ff::bank_static(ff::BANK_DONASJON, $ff_bank_each, $row['ff_id'], "Testamentert", $this->id);
					}
				}
				
				// noe til angriper?
				if ($f_up > 0)
				{
					$ret['penger_bank'] = bcmul($bank, $f_up);
					$by_up->data['up_cash'] = bcadd($by_up->data['up_cash'], $ret['penger_bank']);
					ess::$b->db->query("UPDATE users_players SET up_cash = up_cash + {$ret['penger_bank']} WHERE up_id = $by_up->id");
				}
			}
		}
		
		$info = ($instant ? 'ble drept av '.$by_up->data['up_name'] : ($by_up ? 'd�de av skadene p�f�rt av '.$by_up->data['up_name'] : 'd�de pga. lav helse og energi'));
		
		putlog("INFO", "%bDrept%b: Spilleren {$this->data['up_name']} ".($instant ? 'ble drept ' : 'd�de av skader ').$this->generate_profile_url());
		putlog("DF", "%bDrept%b: Spilleren {$this->data['up_name']} $info ".$this->generate_minside_url());
		
		// hent familier
		$familier = array();
		$list = $this->get_ff_list();
		foreach ($list as $row)
		{
			if ($row['ff_type'] != 1 || $row['ff_is_crew'] != 0) continue;
			$familier[] = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a>';
		}
		
		// live-feed
		livefeed::add_row('<user id="'.$this->id.'" />'.(count($familier) > 0 ? ' (medlem av '.implode(", ", $familier).')' : '').' '.($instant ? 'ble drept' : ($by_up ? 'd�de av et tidligere angrep' : 'd�de pga. lav helse og energi')).'.');
		
		$ret = array_merge($ret, $this->release_relations($prev_level, $by_up, $instant));
		
		// behandle hitlist
		$ret = array_merge($ret, etterlyst::player_dies($this, $by_up, $instant));
		
		// informer spilleren p� e-post
		$email = new email();
		$email->text = 'Hei,

Din spiller '.$this->data['up_name'].' '.($instant ? 'har blitt drept av en annen spiller' : 'd�de p� grunn av lav energi og lav helse').'.

Du kan se informasjon om din spiller og opprette en ny spiller ved � logge inn p� din bruker.

--
www.kofradia.no';
		$email->send($this->user->data['u_email'], "Din spiller {$this->data['up_name']} ".($instant ? 'har blitt drept' : 'er d�d'));
		
		// legg til hendelse hos spilleren
		$this->add_log("dead", $instant ? 1 : 0);
		
		return $ret;
	}
	
	/**
	 * Frigj�r relasjonene en spiller har i spillet
	 * Oppdaterer ogs� ranklisten
	 * @param int $previous_level spillerniv�et f�r d�d/deaktivering
	 */
	protected function release_relations($previous_level, player $up_attack = null, $instant = null)
	{
		// oppdater ranklisten
		/*if ($previous_level < ess::$g['access_noplay'])
			ess::$b->db->query("
				UPDATE users_players, (SELECT up_id ref_up_id FROM users_players WHERE up_points = {$this->data['up_points']} AND up_id != $this->id AND up_access_level < ".ess::$g['access_noplay']." LIMIT 1) ref
				SET up_rank_pos = GREATEST(1, up_rank_pos - 1) WHERE ref_up_id IS NULL AND up_points < {$this->data['up_points']}");*/
		
		ess::$b->db->query("UPDATE users_players_rank SET upr_up_access_level = 0 WHERE upr_up_id = $this->id");
		ranklist::update();
		
		$ret = $this->release_relations_low_health(true, $up_attack, $instant);
		
		// overf�r ansvar for bomberom
		$result = ess::$b->db->query("
			SELECT up_id
			FROM users_players
			WHERE up_brom_up_id = $this->id");
		if (mysql_num_rows($result) > 0)
		{
			// TODO: skal spilleren som overtar ansvaret f� noen beskjed?
			// TODO: hvis ingen overtar ansvaret: skal spilleren som mister ansvarsspiller f� beskjed om dette?
			
			// skal vi gi ansvaret videre?
			$resp = $this->data['up_brom_up_id']
				? "IF(up_id = {$this->data['up_brom_up_id']}, NULL, {$this->data['up_brom_up_id']})"
				: "NULL";
			
			// sett nytt ansvar
			ess::$b->db->query("
				UPDATE users_players
				SET up_brom_up_id = $resp
				WHERE up_brom_up_id = $this->id");
		}
		
		// fjern ansvaret for denne spilleren
		if ($this->data['up_brom_up_id'])
		{
			ess::$b->db->query("
				UPDATE users_players
				SET up_brom_up_id = NULL
				WHERE up_id = $this->id");
		}
		
		// fjern fra aktive auksjoner
		auksjon::player_release($this);
		
		// fjern fra poker
		poker_round::player_dies($this);
		
		return $ret;
	}
	
	/**
	 * Frigj�r relasjon grunnet lav helse
	 */
	public function release_relations_low_health($release_all = null, player $up_attack = null, $instant = null)
	{
		$ret = array(
			"ffm"  => array()
		);
		
		// behandle FF
		essentials::load_module("ff");
		$result = ess::$b->db->query("
			SELECT ff_id
			FROM ff_members
				JOIN ff ON ffm_ff_id = ff_id AND ff_inactive = 0".($release_all ? "" : " AND ff_is_crew = 0")."
			WHERE ffm_up_id = $this->id AND ffm_status != ".ff_member::STATUS_KICKED." AND ffm_status != ".ff_member::STATUS_DEACTIVATED);
		$lost = false;
		while ($row = mysql_fetch_assoc($result))
		{
			$ff = ff::get_ff($row['ff_id'], ff::LOAD_SCRIPT);
			if ($ff && isset($ff->members['list'][$this->id]))
			{
				$ffm = $ff->members['list'][$this->id];
				if ($ffm->data['ffm_status'] == ff_member::STATUS_MEMBER)
				{
					$ret['ffm'][] = $ffm;
				}
				
				$ffm->remove_player(true, $instant ? $up_attack : null);
			}
			$lost = true;
		}
		
		// oppdater tidspunkt for n�r man mistet FF
		if ($lost)
		{
			ess::$b->db->query("UPDATE users_players SET up_health_ff_time = 0 WHERE up_id = $this->id");
		}
		
		// fjern fra aktive auksjoner for firma
		if (!$release_all) auksjon::player_release($this, null, auksjon::TYPE_FIRMA);
		
		return $ret;
	}
	
	/**
	 * Hent ut energiprosent
	 */
	public function get_energy_percent()
	{
		return $this->data['up_energy'] / $this->data['up_energy_max'] * 100;
	}
	
	/**
	 * Hente ut helseprosent
	 */
	public function get_health_percent()
	{
		return $this->data['up_health'] / $this->data['up_health_max'] * 100;
	}
	
	/**
	 * Hent ut beskyttelsesprosent
	 */
	public function get_protection_percent()
	{
		if (!$this->data['up_protection_id']) return false;
		return $this->data['up_protection_state'] * 100;
	}
	
	/**
	 * Generer adresse til profil
	 */
	public function generate_profile_url($absolute = true, $with_id = false)
	{
		global $__server;
		$pre = $absolute ? $__server['path'] : $__server['relative_path'];
		return "$pre/p/".rawurlencode($this->data['up_name']).($with_id ? "/$this->id" : "");
	}
	
	/**
	 * Generer adresse til min side
	 */
	public function generate_minside_url($absolute = true)
	{
		global $__server;
		$pre = $absolute ? $__server['path'] : $__server['relative_path'];
		return "$pre/min_side?up_id=$this->id";
	}
	
	/**
	 * Sett ned energien til spilleren
	 */
	public function energy_use($value)
	{
		$value = (int) $value;
		
		ess::$b->db->query("UPDATE users_players SET up_energy = GREATEST(0, up_energy - $value) WHERE up_id = $this->id");
		$this->data['up_energy'] = max(0, $this->data['up_energy'] - $value);
		
		$this->trigger("energy", array(
				"energy_used" => $value));
	}
	
	/**
	 * Sjekk om vi har nok energi for � utf�re en handling
	 */
	public function energy_check($value)
	{
		return $value < $this->data['up_energy'];
	}
	
	/**
	 * Krev at vi har minimum s� mye energi
	 */
	public function energy_require($value)
	{
		if ($this->energy_check($value)) return;
		
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">
		Lav energi
		<span class="left"></span><span class="right"></span>
	</h1>
	<div class="bg1 c">
		<p>Du har for lav energi.</p>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Sjekk om vi er i fengsel
	 * @return bool
	 */
	public function fengsel_check()
	{
		return $this->data['up_fengsel_time'] > time();
	}
	
	/**
	 * Sjekk hvor lenge vi er i fengsel hvis vi er i fengsel
	 * @return int
	 */
	public function fengsel_wait()
	{
		return max(0, $this->data['up_fengsel_time'] - time());
	}
	
	/**
	 * Krev at vi ikke er i fengsel (vis informasjon hvis vi er i fengsel og avbryt siden)
	 */
	public function fengsel_require_no($load_page = true)
	{
		$wait = $this->fengsel_wait();
		if ($wait == 0) return false; // ikke i fengsel
		
		global $__server;
		if ($load_page) ess::$b->page->add_title("I fengsel");
		
		// i fengsel
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">
		Fengsel
		<span class="left2"></span><span class="right2"></span>
	</h1>
	<div class="bg1 c">
		<p style="float: left; margin: 10px 10px 10px 0"><img src="'.STATIC_LINK.'/other/fengsel.png" alt="I fengsel" style="border: 0 solid #333333" /></p>
		<p style="margin-top: 20px">
			Du sitter i fengsel og slipper ut om '.game::counter($wait, true).'!
		</p>
		<p>
			<a href="'.$__server['relative_path'].'/fengsel">Vis oversikt over fengselet &raquo;</a>
		</p>
	</div>
</div>';
		
		if ($load_page) ess::$b->page->load();
		return true;
	}
	
	/**
	 * Juster wanted niv� og sett i fengsel ved sannsynlighet
	 * @param int $rank_points antall rankpoeng man skaffet (grunnlag for hvor mye wanted niv�et g�r opp)
	 * @param bool $success var handlingen vellykket slik at man ikke skal kunne komme i fengsel?
	 * @param bool $force tving spilleren i fengsel
	 * @param int $force_time tidspunkt n�r spiller skal slippes l�s n�r man tvinger spilleren i fengsel
	 */
	public function fengsel_rank($rank_points, $success = false, $force = false, $force_time = NULL)
	{
		// finn ut hvor mye vi skal �ke
		$increase = 100;
		if ($rank_points >= 0 && $rank_points <= 50)
		{
			$increase = $rank_points * 2 + 5;
		}
		
		// �k wanted level
		ess::$b->db->query("UPDATE users_players SET up_wanted_level = LEAST(1000, up_wanted_level * 1.10 + $increase) WHERE up_id = $this->id");
		
		// hent wanted level og test fengsel
		$result = ess::$b->db->query("SELECT up_wanted_level, up_fengsel_time FROM users_players WHERE up_id = $this->id");
		
		$old_wanted_level = $this->data['up_wanted_level'];
		$this->data['up_wanted_level'] = mysql_result($result, 0);
		$this->data['up_fengsel_time'] = mysql_result($result, 0, 1);
		
		$go_fengsel = $force || (!$success && rand(200, 999) < $this->data['up_wanted_level']);
		$fengsel_time_wait = $this->fengsel_wait();
		
		$fengsel_time_wait_new = ($force_time ? (int) $force_time : ceil($this->data['up_wanted_level']/3));
		$fengsel_time_new = time() + $fengsel_time_wait_new;
		
		$change = $this->data['up_wanted_level'] - $old_wanted_level;
		
		// sette i fengsel eller forlenge tiden?
		$fengsel = false;
		if ($go_fengsel && ($fengsel_time_wait == 0 || $fengsel_time_wait < $fengsel_time_wait_new))
		{
			// sett i fengsel
			ess::$b->db->query("
				UPDATE users_players
				SET
					up_fengsel_num = up_fengsel_num + IF(up_fengsel_time <= ".time().", 1, 0),
					up_fengsel_time = ".$fengsel_time_new."/*,
					up_wanted_level = (up_wanted_level - $increase) / 1.10*/
				WHERE up_id = $this->id");
			
			// hent ny data
			$result = ess::$b->db->query("SELECT up_fengsel_num, up_wanted_level FROM users_players WHERE up_id = $this->id");
			
			$this->data['up_fengsel_num'] = mysql_result($result, 0, 0);
			$this->data['up_fengsel_time'] = $fengsel_time_new;
			$this->data['up_wanted_level'] = mysql_result($result, 0, 1);
			
			// gi melding hvis aktiv bruker (den som viser siden)
			if ($fengsel_time_wait == 0 && login::is_active_user($this) && isset(ess::$b->page))
			{
				ess::$b->page->add_message('Du ble tatt og kom i fengsel. Du slipper ut om '.game::counter($fengsel_time_wait_new, true).'. Wanted niv�et er n� p� '.game::format_num($this->data['up_wanted_level']/10, 1).' %.', null, null, "fengsel");
			}
			
			$fengsel = true;
		}
		
		// trigger
		$this->trigger("fengsel_rank", array(
			"fengsel" => $fengsel,
			"fengsel_time" => $fengsel ? $fengsel_time_wait_new : 0,
			"wanted_level_old" => $old_wanted_level,
			"wanted_level_change" => $change));
		
		return $fengsel ? false : $change;
	}
	
	/**
	 * Sjekk status p� fengseldus�r
	 */
	public function fengsel_dusor_check()
	{
		// er vi ute av fengsel men har dus�r?
		if ($this->data['up_fengsel_time'] < time() && $this->data['up_fengsel_dusor'])
		{
			// gi tilbake pengene
			ess::$b->db->query("
				UPDATE users_players
				SET up_cash = up_cash + up_fengsel_dusor, up_fengsel_dusor = 0
				WHERE up_id = $this->id AND up_fengsel_dusor = {$this->data['up_fengsel_dusor']}");
			
			// ble ingenting endret?
			if (ess::$b->db->affected_rows() == 0) return;
			
			// gi melding om det
			$this->add_log("fengsel_dusor_return", null, $this->data['up_fengsel_dusor']);
			$this->data['up_fengsel_dusor'] = 0;
			$this->data['up_cash'] += $this->data['up_fengsel_dusor'];
		}
	}
	
	/**
	 * Hent ut data
	 */
	public function getd($name)
	{
		if (!array_key_exists($name, $this->data))
		{
			throw new HSException("Data ble ikke funnet.");
		}
		
		return $this->data[$name];
	}
	
	/**
	 * Sjekk om vi er i bomberom
	 */
	public function bomberom_check()
	{
		return $this->data['up_brom_expire'] > time();
	}
	
	/**
	 * Sjekk hvor lenge vi skal v�re i bomberom
	 */
	public function bomberom_wait()
	{
		return max(0, $this->data['up_brom_expire'] - time());
	}
	
	/**
	 * Krev at vi ikke er i bomberom
	 */
	public function bomberom_require_no($load_page = true)
	{
		$wait = $this->bomberom_wait();
		if ($wait == 0) return false; // ikke i bomberom
		
		global $__server;
		if ($load_page) ess::$b->page->add_title("I bomberom");
		
		// i bomberom
		echo '
<div class="bg1_c xsmall" style="width: 460px">
	<h1 class="bg1">
		Bomberom
		<span class="left2"></span><span class="right2"></span>
	</h1>
	<div class="bg1 c" style="color: #BBB">
		<p style="float: right; margin: 10px 0 10px 10px"><img src="'.STATIC_LINK.'/other/bomberom.jpg" alt="I bomberom" style="border: 2px solid #333333" /></p>
		<p style="margin-top: 30px; text-align: center; font-size: 150%">I bomberom</p>
		<p style="margin-top: 20px">Du befinner deg i bomberom frem til '.ess::$b->date->get($this->data['up_brom_expire'])->format(date::FORMAT_SEC).'.</p>
		<p style="margin-top: 20px">'.game::counter($wait, true).' gjenst�r</p>
		<p style="margin-top: 20px"><a href="'.$__server['relative_path'].'/ff/?ff_id='.$this->data['up_brom_ff_id'].'">Vis mer informasjon &raquo;</a></p>
	</div>
</div>';
		
		if ($load_page) ess::$b->page->load();
		return true;
	}
	
	const ATTACK_TYPE_KILL = 0;
	const ATTACK_TYPE_UTPRESSING = 1;
	
	/**
	 * Sett ned helsen til spilleren etter skade utf�rt av en annen spiller
	 * @param int $miste_helse hvor mye helse som skal settes ned
	 * @param player $up spilleren som for�rsaker dette
	 * @param int $attack_type hva slags angrep det var (0=drapsfors�k, 1=utpressing)
	 * @param float $skadeprosent skadeprosenten ved et angrep
	 * @param int $bullets antall kuler benyttet i angrep
	 */
	public function health_decrease($miste_helse, player $up, $attack_type, $skadeprosent = null, $params = array())
	{
		$transaction_before = ess::$b->db->transaction;
		
		// l�s spillerene
		$up->lock();
		$this->lock();
		
		// allerede d�d?
		if (!$this->active)
		{
			if (!$transaction_before) ess::$b->db->commit();
			return false;
		}
		
		$miste_helse = (int) $miste_helse;
		$attack_type = (int) $attack_type;
		
		// d�r spilleren?
		if ($this->data['up_health'] <= $miste_helse)
		{
			// gi rankpoeng
			$rankpoeng = $up->health_calc_rankpoints($this, true, $skadeprosent);
			if ($rankpoeng > 0)
			{
				$rankpoeng = $this->calc_rankpoints_get($rankpoeng);
				$up->increase_rank($rankpoeng, false, true);
			}
			
			// oppdater ff-stats
			$this->attacked_ff_update("killed");
			
			// drep offeret
			$ret = $this->dies(true, $up);
			if (!$ret)
			{
				if (!$transaction_before) ess::$b->db->commit();
				return false;
			}
			
			// gi angriper pengene offeret hadde p� h�nden
			$cash = 0;
			if ($this->data['up_cash'] > 0)
			{
				ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - {$this->data['up_cash']} WHERE up_id = $this->id");
				ess::$b->db->query("UPDATE users_players SET up_cash = up_cash + {$this->data['up_cash']} WHERE up_id = {$up->id}");
				
				$cash = $this->data['up_cash'];
				$this->data['up_cash'] = 0;
				$up->data['up_cash'] += $cash;
			}
			
			// �k telleren over antall drap
			ess::$b->db->query("UPDATE users_players SET up_attack_killed_num = up_attack_killed_num + 1 WHERE up_id = {$up->id}");
			
			// �k telleren over antall drap i familien spilleren er medlem i
			$up->attack_ff_update("killed");
			
			$ret = array_merge($ret, array(
				"drept" => true,
				"rankpoeng" => $rankpoeng, // antall rankpoeng man fikk
				"penger" => $cash // penger offeret hadde p� h�nda
			));
		}
		
		else
		{
			$ret = array();
			
			// hent alle FF angriperen er medlem av eller nylig var medlem av
			$ff_ids = $this->get_ff_id_list();
			
			// sett ny energi og helse for offeret
			$set = "";
			$energi_mistet = $this->data['up_energy'];
			if ($skadeprosent !== null)
			{
				$r = 1 - 0.4 * $skadeprosent;
				$set = "up_energy = up_energy * $r, ";
				$this->data['up_energy'] = round($this->data['up_energy'] * $r);
			}
			$energi_mistet = $energi_mistet - $this->data['up_energy'];
			
			// lagre energi, helse og angriper-FF
			$time = time();
			ess::$b->db->query("
				UPDATE users_players
				SET {$set}up_health = up_health - $miste_helse,
					up_attacked_time = $time, up_attacked_up_id = {$up->id},
					up_attacked_ff_id_list = ".ess::$b->db->quote(implode(",", $ff_ids)).",
					up_health_ff_time = IF(up_health_ff_time IS NULL, up_health_ff_time, IF(up_health / up_health_max >= ".self::FF_HEALTH_LOW.", IF(up_health_ff_time = 0, $time, up_health_ff_time), 0))
				WHERE up_id = $this->id");
			$this->data['up_health'] -= $miste_helse;
			$this->data['up_attacked_time'] = $time;
			$this->data['up_attacked_up_id'] = $up->id;
			
			$ret['drept'] = false;
			$ret['rankpoeng'] = 0;
			$ret['rankpoeng_lost'] = 0;
			
			// sett ny beskyttelse p� offeret
			$prot_mistet = "";
			$ret['protection_replaced'] = false;
			if ($this->protection->data)
			{
				$prot_skadeprosent = $skadeprosent === null ? 0.02 : $skadeprosent; // bruk 2 % som skadeprosent hvis det ikke er angitt (utpressing)
				$prot_mistet = $this->protection->weakened($prot_skadeprosent);
				if ($prot_mistet === false)
				{
					$ret['protection_replaced'] = true;
					$prot_mistet = "";
				}
				else $prot_mistet = round($prot_mistet, 5);
			}
			
			// rettet drapsfors�k?
			$rankpoeng = 0;
			if ($attack_type == self::ATTACK_TYPE_KILL)
			{
				// flytte til tilfeldig bydel
				$moved = false;
				if ($this->data['up_health'] / $this->data['up_health_max'] < self::HEALTH_MOVE_AUTO)
				{
					// finn en tilfeldig bydel
					$result = ess::$b->db->query("SELECT id, name FROM bydeler WHERE active = 1 AND id != {$this->data['up_b_id']} ORDER BY RAND()");
					$moved_from = array("id" => $this->data['up_b_id'], "name" => $this->bydel['name']);
					$moved = mysql_fetch_assoc($result);
					
					$this->data['up_b_id'] = $moved['id'];
					unset($this->bydel);
					ess::$b->db->query("UPDATE users_players SET up_b_id = {$moved['id']} WHERE up_id = $this->id");
				}
				
				// mister penger
				$ret['penger'] = round($this->data['up_cash'] * $miste_helse / $this->data['up_health_max']);
				$extra = "";
				if ($ret['penger'] > 0)
				{
					$up->data['up_cash'] = bcadd($up->data['up_cash'], $ret['penger']);
					$extra = ", up_cash = up_cash + {$ret['penger']}";
					
					$this->data['up_cash'] = bcsub($this->data['up_cash'], $ret['penger']);
					ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - {$ret['penger']} WHERE up_id = $this->id");
				}
				
				// behandle etterlyst
				$ret['hitlist'] = etterlyst::player_hurt($this, $up, $miste_helse / $this->data['up_health_max']);
				
				// �k telleren over antall mislykkede drapsfors�k for angriper
				ess::$b->db->query("UPDATE users_players SET up_attack_damaged_num = up_attack_damaged_num + 1$extra WHERE up_id = {$up->id}");
				
				// �k telleren over antall mislykkede drapsfors�k i familien spilleren er medlem i
				$up->attack_ff_update("damaged");
				
				// oppdater ff-stats
				$this->attacked_ff_update("damaged");
				
				// juster rankpoeng
				$rankpoeng_lost = $up->health_calc_rankpoints($this, false, $skadeprosent);
				if ($rankpoeng_lost > 0)
				{
					$rankpoeng = $this->calc_rankpoints_get($rankpoeng_lost);
					
					$up->increase_rank($rankpoeng, false, true, null, "attack");
					$this->increase_rank(-$rankpoeng_lost, false, true, -$rankpoeng, "attack");
				}
				
				// informer spilleren p� e-post
				$email = new email();
				$email->text = 'Hei,

Din spiller '.$this->data['up_name'].' har blitt angrepet av en annen spiller og du har blitt skadet.

Spilleren har n� '.game::format_num($this->get_health_percent(), 2).' % helse'.($this->protection->data ? ', ' : ' og ').game::format_num($this->get_energy_percent(), 2).' % energi'.($this->protection->data ? ' og '.game::format_num($this->get_protection_percent(), 2).' % beskyttelse' : '').'.

Pass p� s� du ikke risikerer at spilleren bl�r ihjel!

--
www.kofradia.no';
				$email->send($this->user->data['u_email'], "Din spiller {$this->data['up_name']} har blitt angrepet");
				
				// hendelse for spilleren
				$note_data = array(
					// mistet
					round($miste_helse / $this->data['up_health_max'], 5),
					round($energi_mistet / $this->data['up_energy_max'], 5),
					$prot_mistet,
					$rankpoeng,
					
					// nye verdier
					round($this->data['up_health'] / $this->data['up_health_max'], 5),
					round($this->data['up_energy'] / $this->data['up_energy_max'], 5),
					$this->protection->data ? round($this->data['up_protection_state'], 5) : "",
					$this->data['up_points'],
					
					// flyttet mellom bydel
					$moved ? urlencode($moved_from['name']) : "",
					$moved ? urlencode($moved['name']) : "",
					
					// penger mistet
					$ret['penger']
				);
				
				$this->add_log("attacked", implode(":", $note_data), 0);
				
				$ret['rankpoeng'] = $rankpoeng;
				$ret['rankpoeng_lost'] = $rankpoeng_lost;
				
				$ret['health_lost_p'] = $note_data[0];
				$ret['energy_lost_p'] = $note_data[1];
				$ret['protection_lost_p'] = $note_data[2];
				
				$ret['health_lost'] = $miste_helse;
				$ret['energy_lost'] = $energi_mistet;
				
				$ret['health_new_p'] = $note_data[4];
				$ret['energy_new_p'] = $note_data[5];
				$ret['protection_new_p'] = $note_data[6];
			}
			
			// s� lite helse at spilleren mister medlemskap i familie/firma
			if ($this->data['up_health'] / $this->data['up_health_max'] < self::FF_HEALTH_LOW)
			{
				$ret = array_merge($ret, $this->release_relations_low_health(null, $up, true));
			}
		}
		
		// behandle vitner
		// hvis det er en utpressing er det kun vitner hvis spilleren d�r
		$vitner_id = array();
		$vitner = array();
		$vitner_log = array();
		$r = rand(1,100);
		if ($r >= 20 && ($ret['drept'] || $attack_type != self::ATTACK_TYPE_UTPRESSING))
		{
			// antall vitner
			$antall = 1;
			if ($ret['drept'])
			{
				if ($r >= 40) $antall++;
				if ($r >= 60) $antall++;
				if ($r >= 80) $antall++;
			}
			else
			{
				if ($r >= 60) $antall++;
			}
			
			// hent ut tilfeldige vitner
			$timelimit = time() - 86400; // vitnet m� ha v�rt p�logget siste 24 timene
			
			// hent ut tilfeldige vitner
			$result = ess::$b->db->query("
				SELECT up_id
				FROM users_players
				WHERE up_access_level != 0 AND up_access_level < ".ess::$g['access_noplay']." AND up_b_id = {$up->data['up_b_id']} AND up_id NOT IN ({$up->id}, $this->id) AND up_last_online >= $timelimit AND up_brom_expire < ".time()." AND up_fengsel_time < ".time()."
				ORDER BY RAND()
				LIMIT $antall");
			
			while ($row = mysql_fetch_assoc($result))
			{
				$up_vitne = player::get($row['up_id']);
				$row = array(
					"up" => $up_vitne,
					"visible" => rand(1,100) >= 70 // ble vitnet oppdaget av angriper
				);
				
				// send hendelse til vitne
				// drept:attack_type:ble_sett:offer_up_id (num = angriper)
				$up_vitne->add_log("vitne", ($ret['drept'] ? 1 : 0).":$attack_type:".($row['visible'] ? 1 : 0).":".$this->id, $up->id);
				
				$vitner_id[] = $up_vitne->id;
				$vitner[] = $row;
				$vitner_log[] = array($up_vitne->id, $row['visible']);
			}
		}
		
		// hent medlemmer av familie til offeret og sjekk om de skal v�re vitner
		if ($ret['drept'] || ($attack_type != self::ATTACK_TYPE_UTPRESSING))
		{
			// sett opp liste
			$ids = array();
			$list = $this->get_ff_list();
			foreach ($list as $row)
			{
				if ($row['ff_is_crew'] != 0 || $row['ff_type'] != ff::TYPE_FAMILIE) continue;
				$ids[] = $row['ff_id'];
			}
			
			if (count($ids) > 0)
			{
				$limit = 21600; // p�logget innen 6 timer
				$expire = time() - $limit;
				$result = ess::$b->db->query("
					SELECT DISTINCT up_id, up_last_online
					FROM ff_members
						JOIN users_players ON ffm_up_id = up_id AND up_b_id = {$up->data['up_b_id']} AND up_last_online > $expire
					WHERE ffm_ff_id IN (".implode(",", $ids).") AND ffm_status = ".ff_member::STATUS_MEMBER." AND ffm_up_id NOT IN ($this->id, $up->id)");
				
				while ($row = mysql_fetch_assoc($result))
				{
					// er allerede vitne
					if (in_array($row['up_id'], $vitner_id)) continue;
					
					// beregn sannsynlighet
					$prob = min($limit, $row['up_last_online'] - $expire) / $limit * 0.8; // 80 % maks sannsynlighet hvis p�logget akkurat n�
					
					// sjekk sannsynlighet
					if (round($prob*1000) < rand(1, 1000)) continue;
					
					$up_vitne = player::get($row['up_id']);
					$row = array(
						"up" => $up_vitne,
						"visible" => rand(1,100) >= 70 // ble vitnet oppdaget av angriper
					);
					
					// send hendelse til vitne
					// drept:attack_type:ble_sett:offer_up_id (num = angriper)
					$up_vitne->add_log("vitne", ($ret['drept'] ? 1 : 0).":$attack_type:".($row['visible'] ? 1 : 0).":".$this->id, $up->id);
					
					$vitner[] = $row;
					$vitner_log[] = array($up_vitne->id, $row['visible']);
				}
			}
		}
		
		$ret['vitner'] = $vitner;
		if ($params && is_array($params)) $ret = array_merge($ret, $params);
		
		// lagre logg
		$ret['bullets'] = isset($ret['bullets']) ? (int) $ret['bullets'] : 0;
		$this->attack_log($ret, $attack_type, $vitner_log, $up);
		
		// trigger for offer
		if (!$ret['drept'] && $attack_type == self::ATTACK_TYPE_KILL)
		{
			$this->trigger("attacked", array(
					"attack" => $ret,
					"up" => $up));
		}
		
		if (!$transaction_before) ess::$b->db->commit();
		return $ret;
	}
	
	/**
	 * Kalkuler antall rankpoeng man f�r ved � skade en spiller
	 * @param player $up offeret
	 * @param bool $killed ble spilleren drept
	 * @param bool $skadeprosent
	 */
	public function health_calc_rankpoints($up, $killed, $skadeprosent = null)
	{
		// hvilken rank ble angrepet?
		if ($up->rank['pos_id'])
		{
			// spesialrank
			if ($killed) $table = &weapon::$rankpoeng_success_special;
			else $table = &weapon::$rankpoeng_try_special;
			
			$rank_num = count(game::$ranks['pos']) - game::$ranks['pos'][$up->rank['pos_id']]['number'] + 1;
		}
		
		else
		{
			// normal rank
			if ($killed) $table = &weapon::$rankpoeng_success;
			else $table = &weapon::$rankpoeng_try;
			
			$rank_num = $up->rank['number'];
		}
		
		// finn verdien hvis den finnes, hvis ikke bruk den h�yeste verdien
		if (isset($table[$rank_num])) $value = $table[$rank_num];
		else $value = end($table[$rank_num]);
		
		// sett i forhold til rankforskjellen
		$rankdiff = game::calc_rank_diff($this, $up);
		if ($rankdiff < weapon::RANKPOENG_RATIO_LOW) $rankdiff = weapon::RANKPOENG_RATIO_LOW;
		elseif ($rankdiff > weapon::RANKPOENG_RATIO_HIGH) $rankdiff = weapon::RANKPOENG_RATIO_HIGH;
		
		$value *= weapon::$rankpoeng_ratio[$rankdiff];
		
		// sett i perspektiv med skadeprosent
		if (!$killed && $skadeprosent !== null) $value *= $skadeprosent;
		
		return round($value);
	}
	
	/**
	 * Kalkuler sannsynlighet for � finne spilleren
	 */
	public function calc_find_player_prob()
	{
		$prob = 0.75;
		
		// juster sannsynligheten i forhold til reisetid
		if ($this->data['up_b_time'])
		{
			$tid_siden_reise = max(0, min(2700, time() - $this->data['up_b_time']));
			
			$prob *= 0.6 + 0.4 * $tid_siden_reise / 2700;
		}
		
		// juster sannsynligheten i forhold til wanted niv�
		$prob += 0.3 * $this->data['up_wanted_level'] / 1000;
		
		return $prob;
	}
	
	/**
	 * Er spilleren nostat? (Moderator og h�yere)
	 */
	public function is_nostat()
	{
		global $_game;
		return $this->data['up_access_level'] >= $_game['access_noplay'];
	}
	
	/**
	 * Hent ut FF man er med i (eller var med i da helsen var over 40 %)
	 * @return array
	 */
	public function get_ff_list()
	{
		// cachet?
		if (isset($this->ff_list)) return $this->ff_list;
		
		essentials::load_module("ff");
		
		$result = ess::$b->db->query("
			SELECT ff_id, ff_name, ff_type, ff_is_crew, ffm_status, ffm_priority
			FROM ff, ff_members
			WHERE ffm_up_id = $this->id AND ffm_ff_id = ff_id AND ff_inactive = 0 AND (ffm_status = ".ff_member::STATUS_MEMBER." OR (ffm_status = ".ff_member::STATUS_DEACTIVATED." AND (ff_time_reset IS NULL OR ff_time_reset < ffm_date_part)))");
		
		$list = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$list[] = $row;
		}
		
		$this->ff_list = $list;
		return $list;
	}
	
	/**
	 * Hent ut FF-ID
	 */
	public function get_ff_id_list()
	{
		$ff_list = $this->get_ff_list();
		
		$list = array();
		foreach ($ff_list as $row)
		{
			$list[] = $row['ff_id'];
		}
		
		return $list;
	}
	
	/**
	 * Oppdater attack antall for FF
	 * @param string $type
	 */
	public function attack_ff_update($type)
	{
		return ff::attack_update(false, $type, $this->get_ff_id_list());
	}
	
	/**
	 * Oppdater attacked antall for FF
	 * @param string $type
	 */
	public function attacked_ff_update($type)
	{
		return ff::attack_update(true, $type, $this->get_ff_id_list());
	}
	
	/**
	 * Beregn hvor mange rankpoeng vi skal f�
	 */
	public function calc_rankpoints_get($rankpoints)
	{
		// sett i forhold til n�r spilleren var p�logget
		$t = max(0, time() - $this->data['up_last_online']);
		$rankpoints *= pow(0.6, $t / 604800); // eksponentiell synking
		
		// f�r bare 90 % av dette
		return max(50, round($rankpoints * 0.9));
	}
	
	/**
	 * Lagre logg over angrep
	 */
	protected function attack_log($ret, $attack_type, $vitner_log, player $up)
	{
		// sett opp vitner
		$vitner = count($vitner_log) > 0 ? ess::$b->db->quote(serialize($vitner_log)) : 'NULL';
		
		// sett opp liste over FF
		$ff_defend = array();
		$list = $this->get_ff_list();
		foreach ($list as $row)
		{
			if ($row['ff_is_crew'] != 0) continue;
			$type = ff::$types[$row['ff_type']];
			$ff_defend[] = array($row['ff_type'], $row['ff_id'], $type['refobj'], $row['ff_name'], $row['ffm_priority'], $type['priority'][$row['ffm_priority']], $row['ffm_status']);
		}
		$ff_defend = count($ff_defend) > 0 ? ess::$b->db->quote(serialize($ff_defend)) : 'NULL';
		
		// sett opp liste over FF for angriper
		$ff_attack = array();
		$list = $up->get_ff_list();
		foreach ($list as $row)
		{
			if ($row['ff_is_crew'] != 0) continue;
			$type = ff::$types[$row['ff_type']];
			$ff_attack[] = array($row['ff_type'], $row['ff_id'], $type['refobj'], $row['ff_name'], $row['ffm_priority'], $type['priority'][$row['ffm_priority']], $row['ffm_status']);
		}
		$ff_attack = count($ff_attack) > 0 ? ess::$b->db->quote(serialize($ff_attack)) : 'NULL';
		
		// legg til i loggen
		$cash = isset($ret['penger']) ? $ret['penger'] : 0;
		if (isset($ret['penger_bank'])) $cash = bcadd($cash, $ret['penger_bank']);
		$hitlist = isset($ret['hitlist']) ? $ret['hitlist'] : 0;
		ess::$b->db->query("INSERT INTO drapforsok SET df_attack_up_id = {$up->id}, df_defend_up_id = $this->id, df_time = ".time().", df_b_id = ".$up->data['up_b_id'].", df_outcome = ".($ret['drept'] ? 1 : 0).", df_rankpoints = {$ret['rankpoeng']}, df_type = $attack_type, df_cash = $cash, df_hitlist = $hitlist, df_vitner = $vitner, df_attack_ff_list = $ff_attack, df_defend_ff_list = $ff_defend, df_bullets = {$ret['bullets']}");
		
		if ($ret['drept'])
		{
			// logg
			if ($attack_type == self::ATTACK_TYPE_UTPRESSING)
			{
				putlog("DF", "%c4UTPRESSING DREPT%c: {$up->data['up_name']} ({$up->rank['name']}) presset {$this->data['up_name']} ({$this->rank['name']}). ".$this->generate_minside_url());
			}
			else
			{
				putlog("DF", "ANGREP %c4DREPT%c: {$up->data['up_name']} ({$up->rank['name']}) angrep%c3 {$this->data['up_name']}%c ({$this->rank['name']}) med ".$up->weapon->data['name']." (".game::format_number($up->data['up_weapon_training']*100, 2)." % v�pentrening) med ".fwords("%d kule", "%d kuler", $ret['bullets']).". ".$this->generate_minside_url());
				putlog("DF", " - Angrepstyrke: ".game::format_number($ret['attack_skade'][0]*100, 2).", beskyttelsestyrke: ".game::format_number($ret['attack_skade'][1]*100, 2).", skadeprosent: ".game::format_number($ret['skadeprosent']*100, 2)." % av ".weapon::MAX_ATTACK_HEALTH." helsepoeng");
			}
			
			putlog("DF", " - Helse: Hadde ".game::format_number($this->get_health_percent(), 3)." %");
			putlog("DF", " - Energi: Hadde ".game::format_number($this->get_energy_percent(), 3)." %");
			
			if (!$this->protection->data)
			{
				putlog("DF", " - Hadde ingen beskyttelse");
			}
			else
			{
				putlog("DF", " - Beskyttelse: Hadde {$this->protection->data['name']} med ".game::format_number($this->data['up_protection_state']*100, 2)." % styrke");
			}
			
			putlog("DF", " - Rankpoeng: {$up->data['up_name']} fikk ".game::format_number($ret['rankpoeng'])." rankpoeng (".game::format_rank($ret['rankpoeng'], "all")." % rank) ".$up->generate_minside_url());
			
			if (count($ret['vitner']) == 0)
			{
				putlog("DF", " - Ingen vitner");
			}
			else
			{
				foreach ($ret['vitner'] as $vitne)
				{
					putlog("DF", " - Vitne: {$vitne['up']->data['up_name']} (".($vitne['visible'] ? 'ble oppdaget' : 'ble IKKE oppdaget').') '.$vitne['up']->generate_minside_url());
				}
			}
			
			putlog("DF", " - Penger fra h�nda: ".game::format_cash($ret['penger']));
			if (isset($ret['penger_bank'])) putlog("DF", " - Penger fra banken: ".game::format_cash($ret['penger_bank']));
			putlog("DF", " - Penger fra hitlist: ".game::format_cash($ret['hitlist']));
			
			foreach ($ret['ffm'] as $ffm)
			{
				putlog("DF", " - Var ".$ffm->get_priority_name()." i ".$ffm->ff->data['ff_name']." ".ess::$s['path']."/ff/?ff_id={$ffm->ff->id}");
			}
		}
		
		// skadet ved angrep
		elseif ($attack_type == self::ATTACK_TYPE_KILL)
		{
			// logg
			putlog("DF", "ANGREP %c8SKADET%c: {$up->data['up_name']} ({$up->rank['name']}) angrep%c3 {$this->data['up_name']}%c ({$this->rank['name']}) med ".$up->weapon->data['name']." (".game::format_number($up->data['up_weapon_training']*100, 2)." % v�pentrening) med ".fwords("%d kule", "%d kuler", $ret['bullets']).". ".$this->generate_minside_url());
			putlog("DF", " - Angrepstyrke: ".game::format_number($ret['attack_skade'][0]*100, 2).", beskyttelsestyrke: ".game::format_number($ret['attack_skade'][1]*100, 2).", skadeprosent: ".game::format_number($ret['skadeprosent']*100, 2)." % av ".weapon::MAX_ATTACK_HEALTH." helsepoeng");
			putlog("DF", " - Helse: Mistet ".game::format_number($ret['health_lost_p'] * 100, 3)." % ({$ret['health_lost']}) og har n�%c4 ".game::format_number($ret['health_new_p'] * 100, 3)." %");
			putlog("DF", " - Energi: Mistet ".game::format_number($ret['energy_lost_p'] * 100, 3)." % ({$ret['energy_lost']}) og har n�%c12 ".game::format_number($ret['energy_new_p'] * 100, 3)." %");
			
			if ($ret['protection_replaced'])
			{
				putlog("DF", " - Beskyttelsen ble erstattet med ".$this->protection->data['name']);
			}
			elseif ($ret['protection_lost_p'] == "")
			{
				putlog("DF", " - Hadde ingen beskyttelse");
			}
			else
			{
				putlog("DF", " - Beskyttelse: Mistet ".game::format_number($ret['protection_lost_p'] * 100, 3)." % og har n� ".game::format_number($ret['protection_new_p'] * 100, 3)." %");
			}
			
			putlog("DF", " - Rankpoeng: {$up->data['up_name']} fikk ".game::format_num($ret['rankpoeng'])." rankpoeng (offeret mistet ".game::format_num($ret['rankpoeng_lost']).") ".$up->generate_minside_url());
			
			if (count($ret['vitner']) == 0)
			{
				putlog("DF", " - Ingen vitner");
			}
			else
			{
				foreach ($ret['vitner'] as $vitne)
				{
					putlog("DF", " - Vitne: {$vitne['up']->data['up_name']} (".($vitne['visible'] ? 'ble oppdaget' : 'ble IKKE oppdaget').') '.$vitne['up']->generate_minside_url());
				}
			}
			
			putlog("DF", " - Penger fra h�nda: ".game::format_cash($ret['penger']));
			putlog("DF", " - Penger fra hitlist: ".game::format_cash($ret['hitlist']));
		}
	}
	
	/**
	 * Finn adresse til profilbilde
	 */
	public function get_profile_image()
	{
		return self::get_profile_image_static($this->data['up_profile_image_url']);
	}
	
	/**
	 * Hent adresse til profilbilde
	 * @param string $url
	 */
	public static function get_profile_image_static($url)
	{
		if (!$url) return PROFILE_IMAGES_DEFAULT;
		
		if (substr($url, 0, 2) == "l:") return PROFILE_IMAGES_HTTP . "/" . substr($url, 2);
		return $url;
	}
	
	/**
	 * Behandle trigger
	 * @string $name
	 * @array $data
	 */
	public function trigger($name, array $data)
	{
		// behandle oppdrag
		switch ($name)
		{
			case "kriminalitet":
				$this->oppdrag->handle_trigger("kriminalitet_different", $data);
				$this->achievements->handle("kriminalitet", $data);
			break;
			
			case "poker_result":
				$this->oppdrag->handle_trigger("poker_unique_people", $data);
				$this->achievements->handle("poker", $data);
			break;
			
			case "rank_points":
				$this->oppdrag->handle_trigger($name, $data);
				$this->achievements->handle($name, $data);
				hall_of_fame::trigger("rank", $data, $this);
			break;
			
			case "fengsel_rank":
				// kom i fengsel?
				if ($data['fengsel'])
				{
					$this->oppdrag->fengsel();
				}
				$this->oppdrag->handle_trigger("wanted_level", $data);
			break;
			
			case "fengsel":
				$this->achievements->handle($name, $data);
				$this->oppdrag->handle_trigger("fengsel_breakout", $data);
			break;
			
			case "biltyveri":
			case "utpressing":
			case "ff_won_member":
			case "ff_priority_change":
			case "ff_join":
			case "oppdrag":
			case "lotto":
				$this->achievements->handle($name, $data);
			break;
			
			case "money_change":
				$this->achievements->handle($name, $data);
				hall_of_fame::trigger("cash_num", $data, $this);
			break;
			
			case "attack_bleed":
			case "attack":
				$this->achievements->handle($name, $data);
				hall_of_fame::trigger("rank_kill", $data, $this);
				ff::handle_up_kill($this, $data);
			break;
		}
	}
	
	/**
	 * Forandre pengeverdi til spilleren (relativ verdi)
	 * @param int $amount
	 * @param bool $cash true=up_cash false=up_bank
	 * @param bool $update oppdatere users_players tabellen?
	 * @param int/bool $least m� ha minst dette pengebel�pet, evt. true hvis kontrollere at pengeniv�et ikke g�r under 0? (kan uansett ikke g� under 0)
	 */
	public function update_money($amount, $cash = true, $update = true, $least = null)
	{
		$field = $cash ? "cash" : "bank";
		$amount = game::intval($amount);
		
		// oppdatere databasen?
		if ($update)
		{
			$check = "";
			if ($least === true)
			{
				if (bccomp($amount, 0) == -1) $check = " AND up_{$field} >= $amount";
			}
			elseif ($least)
			{
				$least = game::intval($least);
				$check = " AND up_{$field} >= $least";
			}
			
			ess::$b->db->query("UPDATE users_players SET up_{$field} = GREATEST(0, up_{$field} + $amount) WHERE up_id = $this->id$check");
			
			// ingen ting oppdatert?
			if (ess::$b->db->affected_rows() == 0) return false;
		}
		
		// oppdater spillerfelt
		$this->data['up_'.$field] = bcadd($this->data['up_'.$field], $amount);
		
		// behandle trigger
		$this->trigger("money_change", array(
				"field" => $field,
				"amount" => $amount));
	}
	
	/**
	 * Har spiller lov til � sette spiller i bomberom?
	 * @param	int		$up_id
	 * @return	bool
	 */
	public function can_set_brom(player $up)
	{
		// Finn ut om spillere er i samme familie
		$result = ess::$b->db->query("
			SELECT ffm1.ffm_ff_id
			FROM ff_members ffm1
				JOIN ff_members ffm2 ON ffm2.ffm_ff_id = ffm1.ffm_ff_id
			WHERE ffm1.ffm_status = 1
			  AND ffm2.ffm_status = 1
			  AND ffm1.ffm_up_id = $this->id
			  AND ffm2.ffm_up_id = $up->id");
		if (mysql_num_rows($result) > 0) return true;
		
		// Hvis ikke, sjekk rankforskjell
		$rank_diff = $up->rank['id'] - $this->rank['id'];
		if ($rank_diff > 3 || $rank_diff < -3) return false;
		
		return true;
	}
	
	/**
	 * Spiller d�r av lite helse/deaktiverer seg selv
	 */
	public function bleed_handle()
	{
		$expire = time()-3600*4;
		$by_up = null;
		
		// er d�dsfallet innenfor tidspunktet hvor noen kan f� kreditt for det?
		if ($this->data['up_attacked_time'] >= $expire)
		{
			// har vi en spiller som vi skal gi kreditt?
			if ($this->data['up_attacked_up_id'])
			{
				$by_up = player::get($this->data['up_attacked_up_id']);
				if ($by_up)
				{
					// gi kreditt
					ess::$b->db->query("UPDATE users_players SET up_attack_bleed_num = up_attack_bleed_num + 1 WHERE up_id = $by_up->id");
					
					// gi beskjed til spilleren om at denne spillerne bl�dde ihjel
					$by_up->add_log("player_bleed", NULL, $this->id);
				}
			}
			
			// har vi noen FF som skal f� kreditt?
			if ($this->data['up_attacked_ff_id_list'])
			{
				ff::attack_update(false, "bleed", array_map("intval", explode(",", $this->data['up_attacked_ff_id_list'])));
			}
		}
		
		// oppdater ff spilleren er med i
		$this->attacked_ff_update("bleed");
		
		return $by_up;
	}
}
