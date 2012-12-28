<?php

require "base.php";

// TODO: lenke fra min side m� endres

$up = login::$user->player;
if (isset($_GET['up_id']) && ((access::has("mod") && isset($_GET['stats'])) || (access::has("sadmin") && KOFRADIA_DEBUG)))
{
	// fors�k � finn spilleren
	$up = player::get((int) getval("up_id"));
	if (!$up)
	{
		ess::$b->page->add_message("Fant ingen spiller med ID <u>".htmlspecialchars($_GET['up_id'])."</u>.", "error");
		ess::$b->page->load();
	}
	
	redirect::store("poker?up_id=$up->id");
	
	echo '
<p class="c">Du viser pokersiden som tilh�rer '.$up->profile_link().'.'.(!isset($_GET['stats']) ? '<br /><b>Viktig:</b> Utfordringer du gj�r her vil bli gjort som denne spilleren, og ikke din egen.' : '').'</p>';
}

$poker = new page_poker($up);