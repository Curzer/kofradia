<?php

define("ALLOW_GUEST", true);

require "base.php";

global $_game, $_base;
$_base->page->add_title("Betingelser");

// markere betingelsene som sett?
$updated = false;
if (login::$logged_in && (login::$user->data['u_tos_version'] != intval(game::$settings['tos_version']['value']) || empty(login::$user->data['u_tos_accepted_time'])))
{
	$updated = true;
	
	login::$user->data['u_tos_version'] = intval(game::$settings['tos_version']['value']);
	login::$user->data['u_tos_accepted_time'] = time();
	
	ess::$b->db->query("
		UPDATE users
		SET u_tos_version = ".login::$user->data['u_tos_version'].",
			u_tos_accepted_time = ".time()."
		WHERE u_id = ".login::$user->id);
}

echo '
<h1>Betingelser</h1>
<p>Dette er versjon '.game::$settings['tos_version']['value'].' og har v�rt gjeldende siden '.$_base->date->get(game::$settings['tos_update']['value'])->format(date::FORMAT_NOTIME).'.</p>'.(login::$logged_in ? '
<p>'.($updated
	? 'Dette er f�rste gang du viser denne versjonen av betingelsene.'
	: 'Du viste disse betingelsene for f�rste gang '.$_base->date->get(login::$user->data['u_tos_accepted_time'])->format(date::FORMAT_NOTIME).'.'
	).' <b>Ditt videre bruk av tjenesten og nettsiden betyr at du samtykker til disse betingelsene.</b> Hvis du ikke samtykker, m� du <a href="'.ess::$s['rpath'].'/min_side?u&a=deact">avslutte din konto</a> og slutte � bruke tjenesten.</p>
	<p>Brudd p� betingelsene kan f�re til deaktivering og utestengelse.</p>' : '').'
<div id="betingelser_content">'.game::$settings['tos']['value'].'</div>';

$_base->page->load();