<?php

/**
 * System for rutiner
 */
class scheduler
{
	// div variabler
	private $count;
	private $start_exact;
	private $start;
	private $date_now;
	private $date_midnight;
	private $time_midnight;
	private $offset_midnight;
	private $hour;
	private $minute;
	private $second;
	
	/** Utf�r rutiner */
	public function __construct()
	{
		global $_base;
		
		$this->count = 0;
		
		// sett opp tidspunktet for start
		$this->start_exact = microtime(true);
		$this->start = (int) $this->start_exact;
		
		// sett opp div tidsinfo
		$this->date_now = $_base->date->get($this->start);
		
		$this->date_midnight = clone $this->date_now;
		$this->date_midnight->setTime(0, 0, 0);
		
		// s�rg for samme avvik fra GMT (tidssone)
		$gmt_offset_now = $_base->date->timezone->getOffset($this->date_now);
		$gmt_offset_midnight = $_base->date->timezone->getOffset($this->date_midnight);
		$gmt_offset = $gmt_offset_midnight - $gmt_offset_now;
		
		$this->time_midnight = $this->date_midnight->format("U") + $gmt_offset;
		$this->offset_midnight = $this->date_now->format("U") - $this->date_midnight->format("U");
		
		$this->hour = $this->date_now->format("G");
		$this->minute = (int) $this->date_now->format("i");
		$this->second = (int) $this->date_now->format("s");
		
		// hent rutiner som skal utf�res n�
		$result = $_base->db->query("SELECT s_name, s_hours, s_minutes, s_seconds, s_file, s_count, s_previous, s_next FROM scheduler WHERE s_active = 1 AND s_next <= ".$this->start." AND s_expire < ".$this->start);
		
		// kj�r rutiner som ble funnet
		while ($row = mysql_fetch_assoc($result))
		{
			$this->run($row);
		}
		
		// lagre logg hvis noe ble oppdatert
		if ($this->count > 0)
		{
			putlog("LOG", "%bScheduler:%b Ferdig utf�rt (ant: $this->count) (".number_format(round(microtime(true)-$this->start_exact, 6), 6, ",", ".")." sekunder) (script: ".number_format(round(microtime(true)-SCRIPT_START, 6), 6, ",", ".")." sekunder)");
		}
	}
	
	/** Finn tidspunkt for n�r en rutine kan utf�res neste gang  */
	public function next($hours, $minutes, $seconds)
	{
		$parts = array(
			"hours" => $hours,
			"minutes" => $minutes,
			"seconds" => $seconds
		);
		
		// sett opp hvilke tidspunkt som skal behandles (for timer, minutter og sekunder)
		foreach ($parts as $name => $part)
		{
			$h = $name == "hours";
			$max = $h ? 24 : 60;
			$r = array();
			
			// g� gjennom hver del av denne enheten
			foreach (explode(",", $part) as $u)
			{
				// alle tidspunktene?
				if ($u == "*")
				{
					$r = $h ? array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23) : array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59);
					break;
				}
				
				// sjekk for gjentakelser (*/x)
				$matches = false;
				if (preg_match("/^\\*\\/(\\d+)$/D", $u, $matches))
				{
					$n = intval($matches[1]);
					if ($n >= 0 && $n < $max)
					{
						for ($i = 0; $i < $max; $i += $n)
						{
							$r[] = $i;
						}
						continue;
					}
					
					break;
				}
				
				// et bestemt tidspunkt
				$n = intval($u);
				if ($n >= 0 && $n < $max) $r[] = $n;
			}
			
			// fjern mulige gjentakelser og sorter
			$r = array_unique($r);
			sort($r);
			
			// legg til som enhet
			$parts[$name] = count($r) == 0 ? array(0) : $r;
		}
		
		// antall sekunder etter midnatt denne rutinen kan utf�res f�rste gang
		$first = $parts['hours'][0]*3600 + $parts['minutes'][0]*60 + $parts['seconds'][0];
		
		// har vi ikke kommet til f�rste gang enda?
		if ($first > $this->offset_midnight)
		{
			return $this->time_midnight + $first;
		}
		
		// finn f�rste innenfor tiden
		foreach ($parts['hours'] as $hour)
		{
			if ($hour < $this->hour) continue;
			foreach ($parts['minutes'] as $minute)
			{
				if ($hour == $this->hour && $minute < $this->minute) continue;
				foreach ($parts['seconds'] as $second)
				{
					if ($hour == $this->hour && $minute == $this->minute && $second <= $this->second) continue;
					
					// fant gyldig tidspunkt
					return $this->time_midnight + $hour*3600 + $minute*60 + $second;
				}
			}
		}
		
		// vi er etter siste mulighet - benytt f�rste
		$date = clone $this->date_midnight;
		$date->modify("+1 day");
		return $date->format("U") + $first;
	}
	
	/** Kj�r rutine */
	private function run($row)
	{
		global $_base;
		
		// marker som opptatt
		$_base->db->query("UPDATE scheduler SET s_count = s_count + 1, s_previous = $this->start, s_expire = ".($this->start+600)." WHERE s_name = ".$_base->db->quote($row['s_name'])." AND s_count = {$row['s_count']}");
		
		// ikke oppdater - hopp over (en annen holder mest sannsynlig p�)
		if ($_base->db->affected_rows() == 0)
		{
			return;
		}
		
		++$this->count;
		
		// finn ut n�r rutinen skal utf�res neste gang
		$next = $this->next($row['s_hours'], $row['s_minutes'], $row['s_seconds']);
		
		// sjekk om filen finnes
		$path = ROOT . $row['s_file'];
		if (!file_exists($path))
		{
			// lagre logg
			putlog("NOTICE", "%bScheduler:%b ERROR: Scriptfil %u{$row['s_file']}%u for %u{$row['s_name']}%u finnes ikke!");
			sysreport::log("Scheduler - scriptfil mangler", "Scriptfil '{$row['s_file']}' for '{$row['s_name']}' finnes ikke!");
		}
		
		// hent filen
		else
		{
			$start = microtime(true);
			
			// benytt egen funksjon for � hindre overskriving av variabler
			if ($this->load($path) == "skip_next")
			{
				$scheduler_skip_next = true;
			}
			
			$next_r = isset($scheduler_skip_next) ? '' : " - neste: ".$_base->date->get($next)->format(date::FORMAT_SEC)." (".game::timespan($next, game::TIME_ABS | game::TIME_NOBOLD).")";
			putlog("SPAM", "%bScheduler:%b Rutinen %u{$row['s_name']}%u ble utf�rt (".number_format(round(microtime(true)-$start, 6), 6, ",", ".")." sekunder)$next_r");
		}
		
		$s_next = isset($scheduler_skip_next) ? '' : ', s_next = '.$next;
		$_base->db->query("UPDATE scheduler SET s_expire = 0$s_next WHERE s_name = ".$_base->db->quote($row['s_name'])." AND s_count = ".($row['s_count'] + 1));
	}
	
	/** Last inn rutine */
	private function load($path)
	{
		global $scheduler_skip_next;
		require $path;
		if (isset($scheduler_skip_next)) return "skip_next";
	}
}