<?php

require "../base.php";

new page_ff_bank();
class page_ff_bank
{
	/**
	 * FF
	 * @var ff
	 */
	protected $ff;
	
	/**
	 * Skjema
	 * @var form
	 */
	protected $form;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->ff = ff::get_ff();
		$this->ff->needtype("bank");
		$this->ff->needaccess(3);
		
		redirect::store("bank?ff_id={$this->ff->id}");
		ess::$b->page->add_title("Bankkontroll");
		
		$this->page_handle();
		$this->ff->load_page();
	}
	
	/**
	 * Behandle foresp�rsel
	 */
	protected function page_handle()
	{
		$access = $this->ff->access(1);
		$this->form = new form("firma");
		
		ess::$b->page->add_title("Bankkontroll");
		
		// hent antall klienter
		$result = ess::$b->db->query("
			SELECT COUNT(up_id)
			FROM users_players
			WHERE up_access_level != 0 AND up_access_level < ".ess::$g['access_noplay']." AND up_bank_ff_id = {$this->ff->id}");
		$num_klienter = mysql_result($result, 0);
		
		// finn ut n�v�rende status
		$status = $this->ff->params->get("bank_overforing_tap_change", 0);
		$status_text = $status == 0 ? 'Ingen endring' : ($status > 0 ? '�ke '.game::format_number($status*100, 2).' %' : 'Synke '.game::format_number(abs($status)*100, 2).' %');
		
		// finn "tilgjengelige" overf�ringer
		$expire_ffbt = time() - 3600;
		$result = ess::$b->db->query("SELECT COUNT(ffbt_id), SUM(ffbt_amount), SUM(ffbt_profit) FROM ff_bank_transactions WHERE ffbt_ff_id = {$this->ff->id} AND ffbt_up_id = 0 AND ffbt_time >= $expire_ffbt");
		$info = mysql_fetch_row($result);
		
		// n�v�rende overf�ringsgebyr
		$overforing_tap = $this->ff->params->get("bank_overforing_tap", 0);
		
		// forandre fortjeneste?
		if (isset($_POST['fortjenestep_2']) && isset($_POST['fortjenestep_0']) && $access)
		{
			// kontroller verdier
			$fortjenestep_2 = intval($_POST['fortjenestep_2']);
			$fortjenestep_0 = intval($_POST['fortjenestep_0']);
		
			//$this->ff->params_lock();
			$this->ff->params->lock();
			$i = 0;
		
			// medeier
			if ($fortjenestep_2 > 40 || $fortjenestep_2 < 5)
			{
				ess::$b->page->add_message("Fortjenesten for {$this->ff->type['priority'][2]} kan ikke v�re under 5 % eller over 40  %.", "error");
				$i++;
			}
			elseif ($fortjenestep_2 != $this->ff->params->get("fortjenestep_2", 0.25)*100)
			{
				$this->ff->params->update("fortjenestep_2", $fortjenestep_2/100);
				ess::$b->page->add_message("Fortjenesten for ".$this->ff->type['priority'][2]." er n� p� ".$fortjenestep_2." %.");
				$i++;
			}
		
			// �vrige ansatte
			if ($fortjenestep_0 > 40  || $fortjenestep_0 < 5)
			{
				ess::$b->page->add_message("Fortjenesten for �vrige ansatte kan ikke v�re under 5 % eller over 40 %.", "error");
				$i++;
			}
			elseif ($fortjenestep_0 != $this->ff->params->get("fortjenestep_0", 0.10)*100)
			{
				$this->ff->params->update("fortjenestep_0", $fortjenestep_0/100);
				ess::$b->page->add_message("Fortjenesten for �vrige ansatte er n� p� ".$fortjenestep_0." %.");
				$i++;
			}
		
			// ingen som ble endret?
			if ($i == 0)
			{
				ess::$b->page->add_message("Ingen endringer ble utf�rt.");
			}
		
			//$this->ff->params_save();
			$this->ff->params->commit();
			redirect::handle();
		}
		
		
		// hente gebyr?
		if (isset($_POST['hent_gebyr']))
		{
			$this->form->validate(postval("hash"));
			
			// ingen gebyr � hente?
			if ($info[0] == 0)
			{
				ess::$b->page->add_message("Det er ingen gebyr � hente.", "error");
				redirect::handle();
			}
			
			// sjekk at det har g�tt lang nok tid siden forrige gang
			// FIXME: denne er ikke i bruk (bank_gebyr_siste blir aldri satt)
			$expire = $this->ff->uinfo->params->get("bank_gebyr_siste", 0) + 900;
			if ($expire > time())
			{
				ess::$b->page->add_message("Du m� vente ".ess::$b->date->get($expire)->format(date::FORMAT_SEC)." f�r du kan hente nye gebyr.", "error");
				redirect::handle();
			}
			
			ess::$b->db->begin();
			
			// oppdater gebyrene til v�r bruker
			ess::$b->db->query("UPDATE ff_bank_transactions SET ffbt_up_id = ".login::$user->player->id." WHERE ffbt_ff_id = {$this->ff->id} AND ffbt_up_id = 0 AND ffbt_time >= $expire_ffbt");
			
			// finn ut hvor mange prosent vi skal f� og firmaet skal f�
			$p_player = $access ? 0.5 : ($this->ff->access(2) ? $this->ff->params->get("fortjenestep_2", 0.25) : $this->ff->params->get("fortjenestep_0", 0.1));
			$p_firma = max(0, 0.5 - $p_player);
			
			// hent ut informasjon om hvor mye vi fikk
			$result = ess::$b->db->query("
				SELECT COUNT(ffbt_id), SUM(ffbt_amount), SUM(ffbt_profit)*$p_player, SUM(ffbt_profit)*$p_firma
				FROM ff_bank_transactions
				WHERE ffbt_ff_id = {$this->ff->id} AND ffbt_up_id = ".login::$user->player->id." AND ffbt_status = 0");
			$info = mysql_fetch_row($result); // 0 => antall, 1 => overf�rt, 2 => profit bruker, 3 => profit firma
			
			// sett pengene til riktige steder
			ess::$b->db->query("
				UPDATE users_players, ff, ff_bank_transactions, ff_members, (
						SELECT SUM(ffbt_profit) AS ffbt_profit_sum
						FROM ff_bank_transactions
						WHERE ffbt_ff_id = {$this->ff->id} AND ffbt_up_id = ".login::$user->player->id." AND ffbt_status = 0
					) AS ref
				SET up_cash = up_cash + ffbt_profit_sum*$p_player, ff_bank = ff_bank + ffbt_profit_sum*$p_firma, ffbt_status = 1, ffm_earnings = ffm_earnings + ffbt_profit_sum*$p_player, ffm_earnings_ff = ffm_earnings_ff + ffbt_profit_sum*$p_firma
				WHERE ffbt_ff_id = {$this->ff->id} AND ffbt_up_id = ".login::$user->player->id." AND ffbt_status = 0 AND up_id = ffbt_up_id AND ff_id = ffbt_ff_id AND ffm_ff_id = ff_id AND ffm_up_id = up_id");
			
			// oppdater stats
			$this->ff->stats_update("money_in", $info[3]);
			
			// TODO: Slette ffbt oppf�ringene
			
			ess::$b->db->commit();
			
			// ingen ble oppdatert?
			if ($info[0] == 0)
			{
				ess::$b->page->add_message("Det er ingen gebyr � hente.", "error");
			}
			
			// hvor mye vi fikk
			else
			{
				ess::$b->page->add_message("Du hentet {$info[0]} gebyr og fikk totalt ".game::format_cash($info[2]).". ".game::format_cash($info[1])." var blitt overf�rt. Firmaet fikk ".game::format_cash($info[3]).".");
			}
			
			redirect::handle();
		}
		
		
		// finn ut hvor lang tid det er til neste endring
		$date = ess::$b->date->get();
		$next_update = 3600 - $date->format("i")*60 - $date->format("s");
		
		
		// endre overf�ringsgebyr
		if (isset($_POST['eog_value']) && $access)
		{
			// sjekk at verdien er en av de vi kan velge?
			$step = floatval($_POST['eog_value']);
			if (!in_array($step, ff::$type_bank['eog_steps']))
			{
				ess::$b->page->add_message("Verdien du valgte var ikke gyldig.", "error");
				redirect::handle("bank?ff_id={$this->ff->id}");
			}
			
			$this->ff->params->lock();
			$overforing_tap = $this->ff->params->get("bank_overforing_tap", 0);
			
			// �ke?
			if ($step > 0)
			{
				// allerede p� topp?
				if ($overforing_tap >= ff::$type_bank['bank_overforing_gebyr_max'])
				{
					ess::$b->page->add_message("Overf�ringsgebyret kan ikke �kes mer.", "error");
					ess::$b->db->query("COMMIT"); // params lock
					redirect::handle("bank?ff_id={$this->ff->id}");
				}
				
				// overstiger maks?
				if ($step + $overforing_tap > ff::$type_bank['bank_overforing_gebyr_max'])
				{
					$step = ff::$type_bank['bank_overforing_gebyr_max'] - $overforing_tap;
				}
			}
			
			// senke?
			elseif ($step < 0)
			{
				// allerede p� bunn?
				if ($overforing_tap <= ff::$type_bank['bank_overforing_gebyr_min'])
				{
					ess::$b->page->add_message("Overf�ringsgebyret kan ikke senkes mer.", "error");
					ess::$b->db->query("COMMIT"); // params lock
					redirect::handle("bank?ff_id={$this->ff->id}");
				}
				
				// overstiger min?
				if ($step + $overforing_tap < ff::$type_bank['bank_overforing_gebyr_min'])
				{
					$step = ff::$type_bank['bank_overforing_gebyr_min'] - $overforing_tap;
				}
			}
			
			// lagre
			$this->ff->params->update("bank_overforing_tap_change", $step, true);
			
			if ($step == 0)
			{
				ess::$b->page->add_message("Overf�ringsgebyret vil ikke lengre bli endret.");
			}
			else
			{
				$status = $step > 0 ? '�kt med '.game::format_number($step*100, 2).' %' : 'senket med '.game::format_number(abs($step)*100, 2).' %'; 
				ess::$b->page->add_message('Overf�ringsgebyret vil bli '.$status.' til '.game::format_number(($overforing_tap+$step)*100, 2).' % om '.game::counter($next_update).'.');
			}
			
			redirect::handle("bank?ff_id={$this->ff->id}");
		}
		
		
		echo '
<!--<h1>Bankkontroll</h1>-->
<div class="section" style="width: 250px; margin-left: auto; margin-right: auto">
	<h2>Bankinformasjon</h2>'.($access ? '
	<p class="h_right eog_off"><a href="../js" onclick="handleClass(\'.eog_on\', \'.eog_off\', event, this.parentNode.parentNode)">Endre overf�ringsgebyr</a></p>
	<p class="h_right eog_on hide"><a href="../js" onclick="handleClass(\'.eog_off\', \'.eog_on\', event, this.parentNode.parentNode)">Avbryt endringer</a></p>' : '').'
	<dl class="dd_right'.($access ? ' eog_off' : '').'">
		<dt>Overf�ringsgebyr</dt>
		<dd>'.game::format_number($overforing_tap*100, 2).' %</dd>
		<dt>Neste endring</dt>
		<dd>'.$status_text.'</dd>
		<dt>Tid f�r neste endring</dt>
		<dd>'.game::counter($next_update).'</dd>
		<dt>Antall klienter</dt>
		<dd>'.game::format_number($num_klienter).'</dd>
	</dl>';
		
		if ($access)
		{
			echo '
	<form action="" method="post" class="eog_on hide">
		<dl class="dd_right">
			<dt>N�v�rende overf�ringsgebyr</dt>
			<dd>'.game::format_number($overforing_tap*100, 2).' %</dd>
			<dt>N�v�rende status</dt>
			<dd>'.$status_text.'</dd>
			<dt>Ny handling</dt>
			<dd>
				<select name="eog_value">';
			
			$active = in_array($status, ff::$type_bank['eog_steps']) ? $status : 0;
			foreach (ff::$type_bank['eog_steps'] as $step)
			{
				$status = $step == 0 ? 'Ingen endring' : ($step > 0 ? '�ke '.game::format_number($step*100, 2).' %' : 'Senke '.game::format_number(abs($step)*100, 2).' %');
				echo '
					<option value="'.$step.'"'.($step == $active ? ' selected="selected"' : '').'>'.$status.'</option>';
			}
			
			echo '
				</select>
			</dd>
		</dl>
		<p class="c">
			<input type="submit" class="button" value="Lagre endringer" />
			<a href="../js" class="button" onclick="handleClass(\'.eog_off\', \'.eog_on\', event, this.parentNode.parentNode.parentNode)">Avbryt endringer</a>
		</p>
		<div class="hr"></div>
		<p>Overf�ringsgebyret blir endret hver hele time og handlingen fortsetter til du endrer den eller du n�r en av grensene.</p>
		<dl class="dd_right">
			<dt>Minimumsverdi</dt>
			<dd>'.game::format_number(ff::$type_bank['bank_overforing_gebyr_min']*100, 2).' %</dd>
			<dt>Maksimumsverdi</dt>
			<dd>'.game::format_number(ff::$type_bank['bank_overforing_gebyr_max']*100, 2).' %</dd>
		</dl>
	</form>';
		}
		
		echo '
</div>
<div class="section" style="width: 250px; margin-left: auto; margin-right: auto">
	<h2>Overf�ringsgebyr</h2>
	<p class="h_right">
		<a href="../js" onclick="abortEvent(event);hideClass(\'bankinfo0\');showClass(\'bankinfo1\')" class="bankinfo0">Vis informasjon</a>
		<a href="../js" onclick="abortEvent(event);hideClass(\'bankinfo1\');showClass(\'bankinfo0\')" class="bankinfo1 hide">Skjul informasjon</a>
	</p>
	<dl class="dd_right">
		<dt>Uhentede gebyr</dt>
		<dd>'.game::format_number($info[0]).'</dd>

		<dt>&nbsp;</dt>
		<dd>'.game::format_cash($info[2]).'</dd>

		<dt>&nbsp;</dt>
		<dd>('.game::format_cash($info[1]).')</dd>
	</dl>
	<div class="bankinfo1 hide j">
		<div class="hr"></div>
		<p>
			For at banken skal motta overf�ringsgebyrene m� disse hentes inn f�r det har g�tt 60 minutter etter at overf�ringen har skjedd.
		</p>
		<p>
			Den som henter inn gebyrene mottar en viss prosent av gebyrets bel�p:
		</p>
		<ul>
			<li>'.ucfirst($this->ff->type['priority'][1]).': 50 %</li>
			<li>'.ucfirst($this->ff->type['priority'][2]).': '.intval($this->ff->params->get("fortjenestep_2", 0.25)*100).' % ('.intval(50-$this->ff->params->get("fortjenestep_2", 0.25)*100).' % til firmaet)</li>
			<li>�vrige ansatte: '.intval($this->ff->params->get("fortjenestep_0", 0.1)*100).' % ('.intval(50-$this->ff->params->get("fortjenestep_0", 0.1)*100).' % til firmaet)</li></li>
		</ul>
		<p>
			N�r du har hentet inn n�v�rende gebyr m� du vente 15 minutter til neste gang du kan hente inn gebyr.
		</p>
	</div>'.($info[0] > 0 ? '
	<form action="" method="post">
		<input type="hidden" name="hash" value="'.$this->form->create().'" />
		<h4>'.show_sbutton("Hent gebyr", 'name="hent_gebyr"').'</h4>
	</form>' : '').'
</div>';
		
		
		// endre fortjeneste
		if ($access)
		{
			echo '
<div class="section w250 center">
	<h2>Endre fortjeneste</h2>
	<p>Som eier kan du bestemme hvor mange prosent de som henter ut gebyrene skal f�. Du m� velge mellom 5 % og 40 %. Firmaet f�r det som er igjen av totalt 50 %.</p>
	<form action="" method="post">
		<dl class="dd_right dl_2x">
			<dt>'.ucfirst($this->ff->type['priority'][2]).'</dt>
			<dd><input name="fortjenestep_2" type="text" value="'.intval($this->ff->params->get("fortjenestep_2", 0.25)*100).'" class="styled w30 r" /> %</dd>

			<dt>�vrige ansatte</dt>
			<dd><input name="fortjenestep_0" type="text" value="'.intval($this->ff->params->get("fortjenestep_0", 0.1)*100).'" class="styled w30 r" /> %</dd>
		</dl>
		<h4>'.show_sbutton("Lagre endringer").'</h4>
	</form>
</div>';
		}
	}
}
