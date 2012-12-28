<?php

/**
 * Etterlyst (samme som en hitlist)
 */
class page_etterlyst extends pages_player
{
	/**
	 * Construct
	 */
	public function __construct(player $up)
	{
		parent::__construct($up);
		ess::$b->page->add_title("Etterlyst");
		
		$this->handle();
		ess::$b->page->load();
	}
	
	/**
	 * Behandle foresp�rsel
	 */
	protected function handle()
	{
		// legge til spiller?
		if (isset($_GET['add']))
		{
			$this->show_add_player();
		}
		
		// kj�pe ut spiller?
		elseif (isset($_GET['free']))
		{
			$this->show_free_player();
		}
		
		// vise detaljer?
		elseif (isset($_GET['up_id']) && access::has("mod"))
		{
			$this->show_details();
		}
		
		// trekke tilbake dus�r
		elseif (isset($_POST['release']))
		{
			$this->show_release();
		}
		
		else
		{
			$this->show_hitlist();
		}
	}
	
	/**
	 * Vis listen
	 */
	protected function show_hitlist()
	{
		global $__server;
		
		// hent alle oppf�ringene sortert med h�yeste dus�r �verst
		$expire = etterlyst::get_freeze_expire();
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side");
		$result = $pagei->query("
			SELECT hl_up_id, SUM(hl_amount_valid) AS sum_hl_amount_valid, SUM(IF(hl_time < $expire, hl_amount_valid, 0)) AS sum_can_remove
			FROM hitlist
			GROUP BY hl_up_id
			ORDER BY sum_hl_amount_valid DESC");
		
		echo '
<div class="bg1_c xmedium">
	<h1 class="bg1">Etterlyst<span class="left2"></span><span class="right2"></span></h1>
	<p class="h_left"><a href="'.ess::$s['rpath'].'/node/44">Hjelp</a></p>
	<div class="bg1">
		<boxes />';
		
		if ($pagei->total == 0)
		{
			echo '
		<p>Ingen spillere er etterlyst for �yeblikket.</p>';
		}
		
		else
		{
			// sett opp liste over alle spillerene
			$up_ids = array();
			$list = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$up_ids[] = $row['hl_up_id'];
				$list[] = $row;
			}
			
			// hent alle FF hvor spilleren var medlem
			essentials::load_module("ff");
			$result_ff = ess::$b->db->query("
				SELECT ffm_up_id, ffm_priority, ff_id, ff_name, ff_type
				FROM ff_members
					JOIN ff ON ff_id = ffm_ff_id AND ff_inactive = 0 AND ff_is_crew = 0
				WHERE ffm_up_id IN (".implode(",", array_unique($up_ids)).") AND ffm_status = ".ff_member::STATUS_MEMBER."
				ORDER BY ff_name");
			$ff_list = array();
			while ($row = mysql_fetch_assoc($result_ff))
			{
				$pos = ucfirst(ff::$types[$row['ff_type']]['priority'][$row['ffm_priority']]);
				$text = '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'" title="'.htmlspecialchars($pos).'">'.htmlspecialchars($row['ff_name']).'</a>';
				$ff_list[$row['ffm_up_id']][] = $text;
			}
			
			echo '
		<p>Spillere som er etterlyst:</p>
		<table class="table center">
			<thead>
				<tr>
					<th>Spiller</th>
					<th>Broderskap/firma</th>
					<th>Dus�r</th>
					<th>Dus�r som<br />kan kj�pes ut</th>
					<th>&nbsp;</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			foreach ($list as $row)
			{
				$links = array();
				if ($row['hl_up_id'] != $this->up->id) $links[] = '<a href="?add&amp;up_id='.$row['hl_up_id'].'">�k dus�r</a>';
				if ($row['sum_can_remove'] > 0) $links[] = '<a href="?free='.$row['hl_up_id'].'">kj�p ut</a>';
				
				$ff = isset($ff_list[$row['hl_up_id']]) ? implode("<br />", $ff_list[$row['hl_up_id']]) : '&nbsp;';
				
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><user id="'.$row['hl_up_id'].'" /></td>
					<td>'.$ff.'</td>'.(access::has("mod") ? '
					<td class="r"><a href="?up_id='.$row['hl_up_id'].'">'.game::format_cash($row['sum_hl_amount_valid']).'</a></td>' : '
					<td class="r">'.game::format_cash($row['sum_hl_amount_valid']).'</td>').'
					<td class="r">'.game::format_cash($row['sum_can_remove']).'</td>
					<td>'.(count($links) == 0 ? '&nbsp;' : implode(" ", $links)).'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
			
			if ($pagei->pages > 1)
			{
				echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
			}
		}
		
		echo '
		<p>Hvis du skader en spiller som det er satt en dus�r p� vil du motta deler av dus�ren. Hvis denne spilleren d�r vil du motta det gjenst�ende av dus�ren. Det er ikke mulig � kj�pe ut dus�rer som har blitt satt de siste 7 dagene. <a href="'.$__server['relative_path'].'/node/44">Mer informasjon &raquo;</a></p>
		<p><a href="?add">Sett dus�r p� en spiller &raquo;</a></p>
	</div>
</div>';
		
		// hent egne dus�rer
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side_by");
		$result = $pagei->query("
			SELECT hl_id, hl_up_id, hl_time, hl_amount, hl_amount_valid
			FROM hitlist
			WHERE hl_by_up_id = ".$this->up->id."
			ORDER BY hl_time DESC");
		
		if ($pagei->total > 0)
		{
			echo '
<div class="bg1_c small" style="width: 450px">
	<h2 class="bg1">Mine dus�rer<span class="left2"></span><span class="right2"></span></h2>
	<div class="bg1">
		<p>Dette er dus�rene du har plassert p� andre spillere som fremdeles er gyldige. Hvis du velger � trekke en dus�r, f�r du kun igjen <b>50 %</b> av dus�ren.</p>
		<form action="" method="post">
			<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
			<input type="hidden" name="release" />
			<table class="table center">
				<thead>
					<tr>
						<th>Spiller og tidspunkt</th>
						<th>Opprinnelig bel�p</th>
						<th>Gjenst�ende bel�p</th>
					</tr>
				</thead>
				<tbody>';
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
					<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
						<td><input type="radio" name="hl_id" value="'.$row['hl_id'].'" /><user id="'.$row['hl_up_id'].'" /><br /><span class="dark">'.ess::$b->date->get($row['hl_time'])->format().'</span></td>
						<td class="r">'.game::format_cash($row['hl_amount']).'</td>
						<td class="r">'.game::format_cash($row['hl_amount_valid']).'</td>
					</tr>';
			}
			
			echo '
				</tbody>
			</table>
			<p class="c">'.show_sbutton("Trekk tilbake").'</p>
			<p>Gjenst�ende bel�p er det bel�pet som enda ikke er kj�pt ut av andre spillere.</p>
		</form>
	</div>
</div>';
		}
	}
	
	/**
	 * Sette dus�r p� en spiller
	 */
	protected function show_add_player()
	{
		ess::$b->page->add_title("Sett dus�r");
		
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Etterlyst - sett dus�r<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1"><boxes />';
		
		// har vi valgt en spiller?
		$player = false;
		if (isset($_GET['up_id']) || isset($_POST['up_id']) || isset($_POST['up_name']))
		{
			$by_id = isset($_GET['up_id']) ? (int) $_GET['up_id'] : (isset($_POST['up_id']) ? (int) $_POST['up_id'] : false);
			
			// finn spilleren
			$search = "";
			if ($by_id !== false)
			{
				$search = "up_id = $by_id";
			}
			else
			{
				$search = "up_name = ".ess::$b->db->quote($_POST['up_name'])." ORDER BY up_access_level = 0, up_last_online DESC LIMIT 1";
			}
			
			$result = ess::$b->db->query("SELECT up_id, up_name, up_access_level FROM users_players WHERE $search");
			$player = mysql_fetch_assoc($result);
			
			// fant ikke?
			if (!$player)
			{
				ess::$b->page->add_message("Fant ikke spilleren.", "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
			}
			
			// d�d spiller?
			if ($player && $player['up_access_level'] == 0)
			{
				ess::$b->page->add_message('Spilleren <user id="'.$player['up_id'].'" /> er d�d og kan ikke etterlyses.', "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
				$player = false;
			}
			
			// seg selv?
			if ($player && $player['up_id'] == $this->up->id)
			{
				ess::$b->page->add_message("Du kan ikke sette dus�r p� deg selv.", "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
				$player = false;
			}
			
			// nostat?
			if ($player['up_access_level'] >= ess::$g['access_noplay'] && !access::is_nostat())
			{
				ess::$b->page->add_message("Du kan ikke sette dus�r p� en nostat.", "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
				$player = false;
			}
			
			// er nostat og pr�ver � sette dus�r p� en spiller som ikke er nostat?
			if (access::is_nostat() && $player['up_access_level'] < ess::$g['access_noplay'] && !access::has("sadmin"))
			{
				ess::$b->page->add_message("Du er nostat og kan ikke sette dus�r p� en vanlig spiller.", "error");
				if ($by_id !== false) redirect::handle("etterlyst?add");
				$player = false;
			}
		}
		
		// bestemme dus�r?
		if ($player)
		{
			// hent eventuelle aktive dus�rer p� spilleren
			$result = ess::$b->db->query("
				SELECT SUM(hl_amount_valid) AS sum_hl_amount_valid
				FROM hitlist
				WHERE hl_up_id = {$player['up_id']}");
			$a = mysql_result($result, 0);
			
			// m� vi vente?
			$wait = false;
			if ($a == 0 && !access::has("admin"))
			{
				// sjekk n�r siste hitlist ble utf�rt
				$last = $this->up->params->get("hitlist_last_new", false);
				if ($last && $last + etterlyst::WAIT_TIME > time())
				{
					$wait = $last + etterlyst::WAIT_TIME - time();
				}
			}
			
			// legge til dus�ren?
			if (isset($_POST['amount']) && !$wait)
			{
				$amount = game::intval($_POST['amount']);
				
				// h�y nok dus�r?
				if ($amount < etterlyst::MIN_AMOUNT_SET)
				{
					ess::$b->page->add_message("Dus�ren m� v�re p� minimum ".game::format_cash(etterlyst::MIN_AMOUNT_SET).".", "error");
				}
				
				else
				{
					ess::$b->db->begin();
					
					// fors�k � trekk fra pengene
					ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - $amount WHERE up_id = ".$this->up->id." AND up_cash >= $amount");
					if (ess::$b->db->affected_rows() == 0)
					{
						ess::$b->page->add_message("Du har ikke nok penger p� h�nda.", "error");
					}
					
					else
					{
						// vellykket
						ess::$b->db->query("INSERT INTO hitlist SET hl_up_id = {$player['up_id']}, hl_by_up_id = ".$this->up->id.", hl_time = ".time().", hl_amount = $amount, hl_amount_valid = $amount");
						ess::$b->db->commit();
						
						// legg til i loggen til spilleren
						player::add_log_static("etterlyst_add", NULL, $amount, $player['up_id']);
						
						putlog("LOG", "ETTERLYST: ".$this->up->data['up_name']." la til dus�r for UP_ID={$player['up_id']} p� ".game::format_cash($amount).'.');
						putlog("INFO", "ETTERLYST: En spiller la til en dus�r for {$player['up_name']} p� ".game::format_cash($amount)." ".ess::$s['path']."/etterlyst");
						
						ess::$b->page->add_message('Du la til '.game::format_cash($amount).' som dus�r for spilleren <user id="'.$player['up_id'].'" />.');
						$this->up->params->update("hitlist_last_new", time(), true);
						
						redirect::handle();
					}
					
					ess::$b->db->commit();
				}
			}
			
			ess::$b->page->add_js_domready('$("select_amount").focus();');
			
			echo '
		<p>Valgt spiller: <user id="'.$player['up_id'].'" /></p>
		<form action="" method="post">
			<input type="hidden" name="up_id" value="'.$player['up_id'].'" />'.(!$a ? '
			<p>Denne spilleren har ingen dus�r tilnyttet seg fra f�r.</p>' : '
			<p>Denne spilleren har allerede en dus�r p� '.game::format_cash($a).'.</p>').($wait ? '
			<p class="error_box">Du m� vente '.game::counter($wait).' f�r du kan plassere en ny spiller p� listen.</p>
			<p class="c"><a href="etterlyst">Avbryt</a></p>' : '
			<dl class="dd_right">
				<dt>'.(!$a ? 'Dus�r' : '�k dus�ren med').'</dt>
				<dd><input type="text" name="amount" id="select_amount" value="'.htmlspecialchars(postval("amount")).'" class="styled w100" /></dd>
			</dl>
			<p class="c">'.show_sbutton($a ? "�k dus�ren" : "Legg til dus�r").'</p>
			<p class="c"><a href="etterlyst">Avbryt</a> - <a href="etterlyst?add">Velg en annen spiller</a></p>
			<p>Hvis du velger � fjerne dus�ren etter du har lagt den til, f�r du kun 50 % igjen. Hvis noen kj�per ut dus�ren f�r du igjen 50 % av den.</p>').'
		</form>';
		}
		
		// velg spiller
		else
		{
			ess::$b->page->add_js_domready('$("select_up_name").focus();');
			
			echo '
		<p>Du m� f�rst velge hvilken spiller du �nsker � legge til dus�r p�.</p>
		<form action="" method="post">
			<dl class="dd_right">
				<dt>Spiller</dt>
				<dd><input type="text" name="up_name" id="select_up_name" value="'.htmlspecialchars(postval("up_name")).'" class="styled w120" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Finn spiller").'</p>
			<p class="c"><a href="etterlyst">Avbryt</a></p>
		</form>';
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Kj�pe ut en spiller
	 */
	protected function show_free_player()
	{
		$up_id = (int) getval("free");
		
		// hent informasjon om spilleren
		$expire = etterlyst::get_freeze_expire();
		$result = ess::$b->db->query("
			SELECT SUM(hl_amount_valid) AS sum_hl_amount_valid, SUM(IF(hl_time < $expire, hl_amount_valid, 0)) AS sum_can_remove
			FROM hitlist
			WHERE hl_up_id = $up_id
			GROUP BY hl_up_id");
		
		$hl = mysql_fetch_assoc($result);
		if (!$hl)
		{
			ess::$b->page->add_message('Spilleren <user id="'.$hl['hl_up_id'].'" /> har ingen dus�r p� seg.', "error");
			redirect::handle();
		}
		
		// kan ikke kj�pe ut noe?
		if ($hl['sum_can_remove'] == 0)
		{
			ess::$b->page->add_message('Du m� vente lenger for � kunne kj�pe ut dus�ren til <user id="'.$up_id.'" />.', "error");
			redirect::handle();
		}
		
		$least = min(max(etterlyst::MIN_AMOUNT_BUYOUT, etterlyst::MIN_AMOUNT_BUYOUT_RATIO * $hl['sum_can_remove']), $hl['sum_can_remove']);
		
		// kj�pe ut?
		if (isset($_POST['amount']))
		{
			$amount = game::intval($_POST['amount']);
			
			// under minstebel�pet?
			if ($amount < $least)
			{
				ess::$b->page->add_message("Bel�pet kan ikke v�re mindre enn ".game::format_cash($least).".", "error");
			}
			
			else
			{
				// beregn kostnad
				$m = $up_id == $this->up->id ? 3 : 2;
				$result = ess::$b->db->query("SELECT $amount * $m, $amount > {$hl['sum_can_remove']}, $amount * $m > ".$this->up->data['up_cash']);
				$price = mysql_result($result, 0);
				
				// for h�yt bel�p?
				if (mysql_result($result, 0, 1))
				{
					ess::$b->page->add_message("Bel�pet var for h�yt.", "error");
				}
				
				// har ikke nok penger?
				elseif (mysql_result($result, 0, 2))
				{
					ess::$b->page->add_message("Du har ikke nok penger p� h�nda. Du m� ha ".game::format_cash($price)." p� h�nda for � kunne betale ut ".game::format_cash($amount).".", "error");
				}
				
				else
				{
					ess::$b->db->begin();
					
					// fors�k � trekk fra pengene
					ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - $price WHERE up_id = ".$this->up->id." AND up_cash >= $price");
					if (ess::$b->db->affected_rows() == 0)
					{
						ess::$b->page->add_message("Du har ikke nok penger p� h�nda. Du m� ha ".game::format_cash($price)." p� h�nda for � kunne betale ut ".game::format_cash($amount).".", "error");
					}
					
					else
					{
						// fors�k � trekk fra pengene fra hitlist
						ess::$b->db->query("SET @t := $amount");
						ess::$b->db->query("
							UPDATE hitlist h, (
								SELECT
									hl_id,
									GREATEST(0, LEAST(@t, hl_amount_valid)) AS to_remove,
									@t := GREATEST(0, @t - hl_amount_valid)
								FROM hitlist
								WHERE hl_up_id = $up_id AND @t > 0 AND hl_time < $expire
								ORDER BY hl_time DESC
							) r
							SET h.hl_amount_valid = h.hl_amount_valid - to_remove
							WHERE h.hl_id = r.hl_id");
						ess::$b->db->query("DELETE FROM hitlist WHERE hl_amount_valid = 0");
						
						// har vi noe til overs?
						$result = ess::$b->db->query("SELECT @t");
						$a = mysql_result($result, 0);
						if ($a > 0)
						{
							ess::$b->db->rollback();
							ess::$b->page->add_message("Bel�pet var for h�yt.", "error");
						}
						
						else
						{
							ess::$b->db->commit();
							
							putlog("LOG", "ETTERLYST: ".$this->up->data['up_name']." kj�pte ut dus�r for UP_ID=$up_id p� ".game::format_cash($amount).'. Betalte '.game::format_cash($price).'.');
							
							if ($up_id == $this->up->id)
							{
								ess::$b->page->add_message("Du kj�pte ut en dus�r p� ".game::format_cash($amount).' for deg selv. Du m�tte betale '.game::format_cash($price).' for dette.');
							}
							else
							{
								ess::$b->page->add_message("Du kj�pte ut en dus�r p� ".game::format_cash($amount).' for <user id="'.$up_id.'" />. Du m�tte betale '.game::format_cash($price).' for dette.');
							}
							
							redirect::handle();
						}
					}
				}
			}
		}
		
		ess::$b->page->add_js_domready('$("select_amount").focus();');
		
		echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Etterlyst - kj�p ut spiller<span class="left2"></span><span class="right2"></span></h1>
	<div class="bg1"><boxes />
		<dl class="dd_right">
			<dt>Spiller</dt>
			<dd><user id="'.$up_id.'" /></dd>
			<dt>Total dus�r</dt>
			<dd>'.game::format_cash($hl['sum_hl_amount_valid']).'</dd>
			<dt>Dus�r som kan kj�pes ut</dt>
			<dd>'.game::format_cash($hl['sum_can_remove']).'</dd>
		</dl>
		<form action="" method="post">
			<input type="hidden" name="up_id" value="'.$up_id.'" />
			<dl class="dd_right">
				<dt>Dus�r � kj�pe ut</dt>
				<dd><input type="text" name="amount" id="select_amount" value="'.htmlspecialchars(postval("amount", game::format_cash($hl['sum_can_remove']))).'" class="styled w100" /></dd>
			</dl>
			<p class="c">'.show_sbutton("Kj�p ut").'</p>
			<p class="c"><a href="etterlyst">Avbryt</a> - <a href="etterlyst?add">Velg en annen spiller</a></p>
			<p>'.($up_id == $this->up->id
				? 'Du m� betale 3 ganger bel�pet du velger � kj�pe ut for n�r du kj�per ut deg selv.'
				: 'Du m� betale det dobbelte av bel�pet du velger � kj�pe ut en annen spiller for.').'</p>
		</form>
	</div>
</div>';
	}
	
	/**
	 * Vis detaljer
	 */
	protected function show_details()
	{
		if (empty($_GET['up_id']) || !access::has("mod")) redirect::handle();
		
		// last inn spiller
		$up_id = (int) $_GET['up_id'];
		$up = player::get($up_id);
		
		if (!$up)
		{
			ess::$b->page->add_message("Ingen spiller med id $up_id.", "error");
			redirect::handle();
		}
		
		$pagei = new pagei(pagei::PER_PAGE, 30, pagei::ACTIVE_GET, 'side');
		$result = $pagei->query("SELECT hl_id, hl_up_id, hl_by_up_id, hl_time, hl_amount, hl_amount_valid FROM hitlist WHERE hl_up_id = $up->id AND hl_amount_valid > 0 ORDER BY hl_time DESC");
		
		echo '
<div class="bg1_c medium">
	<h1 class="bg1">
		Etterlyst - '.$up->data['up_name'].'
		<span class="left"></span><span class="right"></span>
	</h1>
	<p class="h_left"><a href="etterlyst">&laquo; Tilbake</a></p>
	<div class="bg1">
		<p>Denne listen viser info om alle som har lagt til dus�r p� spilleren '.$up->profile_link().'.</p>';
		
		if ($pagei->total == 0)
		{
			echo '
		<p><b>Det er ingen som har satt dus�r p� denne spilleren.</b></p>';
		}
		
		else
		{
			echo '
		<table class="table center'.($pagei->pages == 1 ? ' tablemb' : '').'">
			<thead>
				<tr>
					<th>Satt av</th>
					<th>Tid</th>
					<th>Opprinnelig dus�r</th>
					<th>Gjenst�ende dus�r</th>
				</tr>
			</thead>
			<tbody>';
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
				<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
					<td><user id="'.$row['hl_by_up_id'].'" /></td>
					<td>'.ess::$b->date->get($row['hl_time'])->format().'</td>
					<td class="r">'.game::format_cash($row['hl_amount']).'</td>
					<td class="r">'.game::format_cash($row['hl_amount_valid']).'</td>
				</tr>';
			}
			
			echo '
			</tbody>
		</table>';
			
			if ($pagei->pages > 1)
			{
				echo '
		<p class="c">'.$pagei->pagenumbers().'</p>';
			}
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Trekk tilbake dus�r
	 */
	protected function show_release()
	{
		if (!isset($_POST['hl_id']))
		{
			ess::$b->page->add_message("Du m� velge en dus�r du har satt.", "error");
			redirect::handle();
		}
		
		$hl_id = (int) $_POST['hl_id'];
		
		// hent informasjon
		$result = ess::$b->db->query("SELECT hl_up_id, hl_time, hl_amount, hl_amount_valid FROM hitlist WHERE hl_id = $hl_id AND hl_by_up_id = ".$this->up->id." AND hl_amount_valid > 0");
		$hl = mysql_fetch_assoc($result);
		
		if (!$hl)
		{
			ess::$b->page->add_message("Fant ikke oppf�ringen.", "error");
			redirect::handle();
		}
		
		ess::$b->db->begin();
		
		// slett oppf�ringen
		ess::$b->db->query("DELETE FROM hitlist WHERE hl_id = $hl_id AND hl_amount_valid = {$hl['hl_amount_valid']}");
		if (ess::$b->db->affected_rows() == 0)
		{
			ess::$b->page->add_message("Noen kom deg i forkj�pet og kj�pte ut hele eller deler av dus�ren.", "error");
			ess::$b->db->commit();
			redirect::handle();
		}
		
		// hvor mye penger skal vi f�?
		$result = ess::$b->db->query("SELECT ROUND({$hl['hl_amount_valid']}/2)");
		$amount = mysql_result($result, 0);
		
		// gi penger
		ess::$b->db->query("UPDATE users_players SET up_cash = up_cash + $amount WHERE up_id = ".$this->up->id);
		ess::$b->db->commit();
		
		putlog("LOG", "ETTERLYST: ".$this->up->data['up_name']." trakk tilbake dus�r for UP_ID={$hl['hl_up_id']} p� ".game::format_cash($hl['hl_amount_valid']).'.');
		
		ess::$b->page->add_message('Du trakk tilbake dus�ren p� <user id="'.$hl['hl_up_id'].'" /> som ble satt '.ess::$b->date->get($hl['hl_time'])->format().' og som hadde igjen '.game::format_cash($hl['hl_amount_valid']).'. Du fikk tilbake '.game::format_cash($amount).'.');
		redirect::handle();
	}
}