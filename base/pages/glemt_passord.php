<?php

class page_glemt_passord
{
	/**
	 * Hvor lenge autologin er aktiv
	 */
	const AUTOLOGIN_TIME = 900; // 15 minutter
	
	/**
	 * Ventetid f�r man kan benytte glemt passord p� nytt
	 */
	const WAIT = 600; // 10 minutter
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		ess::$b->page->add_title("Glemt passord");
		access::no_user();
		
		ess::$b->page->theme_file = "guest_simple";
		sess_start();
		
		// behandle foresp�rsel?
		if (isset($_POST['epost']))
		{
			$this->handle();
		}
		
		// vis siden
		$this->show();
		
		ess::$b->page->load();
	}
	
	/**
	 * Send lenke med autologin
	 */
	protected function handle()
	{
		// mangler eller ugyldig n�kkel?
		if (!isset($_POST['key']) || !isset($_SESSION['glemtpassord_key']) || $_POST['key'] != $_SESSION['glemtpassord_key'])
		{
			ess::$b->page->add_message("Ugyldig foresp�rsel. Pr�v p� nytt.", "error");
			return;
		}
		
		// sjekk e-post
		$epost = trim(postval("epost"));
		if (empty($epost))
		{
			ess::$b->page->add_message("Du m� fylle inn e-postadressen din.", "error");
			return;
		}
		
		// sjekk om det er noen oppf�ringer med denne e-posten i databasen (og hent de som lever)
		$result = ess::$b->db->query("SELECT u_id, up_name, up_access_level, u_pass_change, u_email FROM users LEFT JOIN users_players ON u_active_up_id = up_id WHERE u_email = ".ess::$b->db->quote($epost)." AND u_access_level != 0");
		
		// ingen?
		if (mysql_num_rows($result) == 0)
		{
			ess::$b->page->add_message('Fant ingen bruker som hadde e-postadressen '.htmlspecialchars($epost).'. Du finner ikke din bruker dersom <u>brukeren</u> er deaktivert. Dersom du har blitt deaktivert og mener dette er feil ta <a href="henvendelser">kontakt</a>.', "error");
			return;
		}
		
		// flere e-postadresser?
		if (mysql_num_rows($result) > 1)
		{
			ess::$b->page->add_message('Det finnes flere brukere som er registrert p� '.htmlspecialchars($epost).'. Vennligst ta <a href="henvendelser">kontakt</a>!', "error");
			redirect::handle();
		}
		
		$row = mysql_fetch_assoc($result);
		
		// er det noen oppf�ring for n�r passordet sist ble oppdatert?
		if (!empty($row['u_pass_change']))
		{
			$info = explode(";", $row['u_pass_change']);
			
			// A;<timestamp>;<ip>[;hash]			-- asked	- bedt om nytt pass
			// C;<timestamp>;<ip>					-- changed	- endret pass
			
			// bedt om pass (A - asked)
			if ($info[0] == "A")
			{
				// n�r?
				$when = intval($info[1]);
				$wait = max(0, $when + self::WAIT - time());
				if ($wait > 0)
				{
					ess::$b->page->add_message("Du benyttet deg av glemt passord ".ess::$b->date->get($when)->format().", og m� vente ".game::timespan($wait, game::TIME_FULL)." f�r du kan benytte deg av glemt passord p� nytt. Se e-posten du skal ha mottatt!", "error");
					return;
				}
			}
		}
		
		// legg inn i databasen
		ess::$b->db->query("UPDATE users SET u_pass_change = 'A;".time().";{$_SERVER['REMOTE_ADDR']}' WHERE u_id = {$row['u_id']}");
		
		// generer autologin
		$hash = autologin::generate($row['u_id'], time()+self::AUTOLOGIN_TIME, null, autologin::TYPE_RESET_PASS);
		
		// send e-post
		$email = new email();
		$email->text = 'Hei,

Du har bedt om � nullstille ditt passord p� '.ess::$s['path'].' fra IP-en '.$_SERVER['REMOTE_ADDR'].' ('.$_SERVER['HTTP_USER_AGENT'].').

Ved � benytte lenken nedenfor vil passordet p� brukeren din bli nullstilt, du blir automatisk logget inn og kan fylle inn ditt nye passord:
'.ess::$s['spath'].'/autologin/'.$hash.'

Hvis du ikke �nsker � nullstille ditt passord kan du se bort fra denne e-posten.

--
www.kofradia.no';
		$email->headers['X-SMafia-IP'] = $_SERVER['REMOTE_ADDR'];
		$email->headers['Reply-To'] = "henvendelse@smafia.no";
		$email->send($epost, "Nullstille ditt passord");
		
		// logg dette
		putlog("NOTICE", "%c7%bNULLSTILLE PASSORD:%b%c %u{$_SERVER['REMOTE_ADDR']}%u ba om e-post for � nullstille passordet %u{$epost}%u (%u{$row['up_name']}%u)");
		
		// gi infomelding
		ess::$b->page->add_message("Vi har sendt deg en e-post til <b>".htmlspecialchars($row['u_email'])."</b>.<br />Benytt denne for � nullstille passordet ditt.");
		redirect::handle("", redirect::ROOT);
	}
	
	/**
	 * Vis siden
	 */
	protected function show()
	{
		// css
		ess::$b->page->add_css('
.gp_wrap {
	margin-top: 30px;
	text-align: center;
}
#gp_epost {
	margin: 0 20px;
}
');
		
		// generer unik n�kkel
		$key = substr(md5(uniqid("")), 0, 8);
		$_SESSION['glemtpassord_key'] = $key;
		
		ess::$b->page->add_js_domready('$("gp_epost").focus();');
		
		echo '
<p>Hvis du har glemt passordet ditt har du her mulighet til � nullstille det. Du trenger din e-postadresse og mulighet for � lese e-posten din.</p>
<p>N�r du benytter deg av denne funksjonen, vil du bli tilsendt en lenke p� e-posten din. Denne lenken vil automatisk logge deg inn og nullstille passordet ditt. Du m� deretter opprette et nytt passord for � kunne benytte hele siden.</p>
<div class="gp_wrap">
	<form action="" method="post">
		<input type="hidden" name="key" value="'.$key.'" />
		<p class="gp_felt">
			E-postadresse
			<input type="text" name="epost" value="'.htmlspecialchars(postval("email")).'" class="styled w150" id="gp_epost" />
			'.show_sbutton("Motta e-post").'
		</p>
	</form>
</div>';
	}
}