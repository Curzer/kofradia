<?php

require "base.php";
global $_base;

$_base->page->add_title("Kontakter");

// legg til
if (isset($_GET['add']))
{
	// type
	$type = $_GET['add'] == "contact" ? 1 : ($_GET['add'] == "block" ? 2 : 0);
	if ($type == 0)
	{
		redirect::handle();
	}
	
	// spillerid
	if (!isset($_GET['id']))
	{
		redirect::handle();
	}
	$up_id = intval($_GET['id']);
	
	// hent info
	$result = $_base->db->query("SELECT up_id, up_name, up_access_level FROM users_players WHERE up_id = $up_id");
	$player = mysql_fetch_assoc($result);
	if (!$player)
	{
		$_base->page->add_message("Fant ikke spilleren.", "error");
		redirect::handle();
	}
	
	// d�d?
	if ($player['up_access_level'] == 0)
	{
		$_base->page->add_message('Spilleren <user id="'.$player['up_id'].'" /> er d�d og kan ikke legges til.', "error");
		redirect::handle("/p/".rawurlencode($player['up_name'])."/".$player['up_id'], redirect::ROOT);
	}
	
	// meg selv?
	if ($player['up_id'] == login::$user->player->id)
	{
		$_base->page->add_message("Du kan ikke legge til deg selv.", "error");
		redirect::handle("/p/".rawurlencode($player['up_name'])."/".$player['up_id'], redirect::ROOT);
	}
	
	// avbryte?
	if (isset($_POST['abort']))
	{
		redirect::handle("/p/".rawurlencode($player['up_name'])."/".$player['up_id'], redirect::ROOT);
	}
	
	// allerede lagt til?
	if (isset(login::$info['contacts'][$type][$player['up_id']]))
	{
		$_base->page->add_message('<user id="'.$player['up_id'].'" /> er allerede i listen.', "error");
		redirect::handle();
	}
	
	// har vi info?
	if (isset($_POST['add']))
	{
		// begrunnelse
		$info = trim(postval("info"));
		$text = strip_tags(game::bb_to_html($info));
		
		// for lang?
		if (strlen($text) > 200)
		{
			$_base->page->add_message("Informasjonen var for lang. Kan ikke v�re mer enn 200 tegn (uten BB koder).", "error");
		}
		
		// ugyldig?
		elseif (!isset($_POST['sid']) || $_POST['sid'] != login::$info['ses_id'])
		{
			$_base->page->add_message("Ugyldig.", "error");
		}
		
		else
		{
			// legg til
			$_base->db->query("INSERT IGNORE INTO users_contacts SET uc_u_id = ".login::$user->id.", uc_contact_up_id = {$player['up_id']}, uc_time = ".time().", uc_type = $type, uc_info = ".$_base->db->quote($info));
			
			if ($type == 1)
			{
				$_base->page->add_message('<user id="'.$player['up_id'].'" /> er n� lagt til i din kontaktliste.');
			}
			else
			{	
				$_base->page->add_message('<user id="'.$player['up_id'].'" /> er n� blokkert.');
			}
			
			$_base->db->query("UPDATE users SET u_contacts_update_time = ".time()." WHERE u_id = ".login::$user->id);
			redirect::handle();
		}
	}
	
	// vis formen
	echo '
<h1>Legg til '.($type == 1 ? 'kontakt' : 'blokkering').'</h1>
<form action="" method="post">
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<div class="section" style="width: 270px; margin-left: auto; margin-right: auto">
		<h2>Informasjon</h2>
		<dl class="dl_30 dl_2x">
			<dt>Spiller</dt>
			<dd>'.game::profile_link($player['up_id'], $player['up_name'], $player['up_access_level']).'</dd>
			
			<dt>Type</dt>
			<dd>'.($type == 1 ? 'Kontakt' : 'Blokkering').'</dd>
			
			<dt>'.($type == 1 ? 'Informasjon' : 'Begrunnelse').'</dt>
			<dd>
				<textarea name="info" rows="5" cols="25" style="width: 165px" id="ptx">'.htmlspecialchars(postval("info")).'</textarea>
			</dd>
			
			<dt'.(isset($_POST['preview']) && isset($_POST['info']) ? '' : ' style="display: none"').' id="pdt">Forh�ndsvisning</dt>
			<dd'.(isset($_POST['preview']) && isset($_POST['info']) ? '' : ' style="display: none"').' id="pdd">'.(!isset($_POST['info']) || empty($_POST['info']) ? 'Tomt?!' : game::bb_to_html($_POST['info'])).'</dd>
			<div class="clear"></div>
		</dl>
		<h3 class="c">
			'.show_sbutton("Legg til", 'name="add"').'
			'.show_sbutton("Avbryt", 'name="abort"').'
			'.show_sbutton("Forh�ndsvis", 'name="preview" onclick="previewDL(event, \'ptx\', \'pdt\', \'pdd\')"').'
		</h3>
	</div>
</form>';
	
	$_base->page->load();
}

// redigere informasjon/begrunnelse
if (isset($_GET['edit']))
{
	// avbryte
	if (isset($_POST['abort'])) redirect::handle();
	
	// hent oppf�ringen
	$id = intval($_GET['edit']);
	$result = $_base->db->query("SELECT uc_id, uc_contact_up_id, uc_type, uc_time, uc_info, up_name, up_access_level, up_last_online FROM users_contacts LEFT JOIN users_players ON up_id = uc_contact_up_id WHERE uc_id = $id AND uc_u_id = ".login::$user->id);
	
	// finnes ikke?
	if (mysql_num_rows($result) == 0)
	{
		$_base->page->add_message("Fant ikke oppf�ringen.", "error");
		redirect::handle();
	}
	
	$row = mysql_fetch_assoc($result);
	
	// lagre?
	if (isset($_POST['save']))
	{
		// begrunnelse
		$info = trim(postval("info"));
		$text = strip_tags(game::bb_to_html($info));
		
		// for lang?
		if (strlen($text) > 200)
		{
			$_base->page->add_message(($row['uc_type'] == 1 ? 'Informasjonen' : 'Begrunnelsen')." var for lang. Kan ikke v�re mer enn 200 tegn (uten BB koder).", "error");
		}
		
		// ugyldig?
		elseif (!isset($_POST['sid']) || $_POST['sid'] != login::$info['ses_id'])
		{
			$_base->page->add_message("Ugyldig.", "error");
		}
		
		else
		{
			// oppdater
			$_base->db->query("UPDATE users_contacts SET uc_info = ".$_base->db->quote($info)." WHERE uc_id = {$row['uc_id']}");
			
			if ($row['uc_type'] == 1)
			{
				$_base->page->add_message('Informasjonen for kontakten <user id="'.$row['uc_contact_up_id'].'" /> ble oppdatert.');
			}
			else
			{	
				$_base->page->add_message('Begrunnelsen for blokkeringen til <user id="'.$row['uc_contact_up_id'].'" /> ble oppdatert.');
			}
			
			$_base->db->query("UPDATE users SET u_contacts_update_time = ".time()." WHERE u_id = ".login::$user->id);
			redirect::handle();
		}
	}
	
	// vis formen
	echo '
<h1>Oppdater '.($row['uc_type'] == 1 ? 'kontakt' : 'blokkering').'</h1>
<form action="" method="post">
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<div class="section" style="width: 270px; margin-left: auto; margin-right: auto">
		<h2>Informasjon</h2>
		<dl class="dl_30 dl_2x">
			<dt>Spiller</dt>
			<dd>'.game::profile_link($row['uc_contact_up_id'], $row['up_name'], $row['up_access_level']).'</dd>
			
			<dt>Type</dt>
			<dd>'.($row['uc_type'] == 1 ? 'Kontakt' : 'Blokkering').'</dd>
			
			<dt>Lagt til</dt>
			<dd>'.$_base->date->get($row['uc_time'])->format(date::FORMAT_SEC).'</dd>
			
			<dt>'.($row['uc_type'] == 1 ? 'Informasjon' : 'Begrunnelse').'</dt>
			<dd>
				<textarea name="info" rows="5" cols="25" style="width: 165px" id="ptx">'.htmlspecialchars(postval("info", $row['uc_info'])).'</textarea>
			</dd>
			
			<dt'.(isset($_POST['preview']) && isset($_POST['info']) ? '' : ' style="display: none"').' id="pdt">Forh�ndsvisning</dt>
			<dd'.(isset($_POST['preview']) && isset($_POST['info']) ? '' : ' style="display: none"').' id="pdd">'.(!isset($_POST['info']) || empty($_POST['info']) ? 'Tomt?!' : game::bb_to_html($_POST['info'])).'</dd>
			<div class="clear"></div>
		</dl>
		<h3 class="c">
			'.show_sbutton("Lagre", 'name="save"').'
			'.show_sbutton("Avbryt", 'name="abort"').'
			'.show_sbutton("Forh�ndsvis", 'name="preview" onclick="previewDL(event, \'ptx\', \'pdt\', \'pdd\')"').'
		</h3>
	</div>
</form>';
	$_base->page->load();
}

// fjern
if (isset($_GET['del']))
{
	// type
	$type = $_GET['del'] == "contact" ? 1 : ($_GET['del'] == "block" ? 2 : 0);
	if ($type == 0)
	{
		redirect::handle();
	}
	
	// ugyldig?
	if (!isset($_GET['sid']) || $_GET['sid'] != login::$info['ses_id'])
	{
		$_base->page->add_message("Ugyldig.", "error");
		redirect::handle();
	}
	
	// spillerid
	if (!isset($_GET['id']))
	{
		redirect::handle();
	}
	$up_id = intval($_GET['id']);
	
	// hent info
	$result = $_base->db->query("SELECT up_id, up_name, up_access_level FROM users_players WHERE up_id = $up_id");
	$player = mysql_fetch_assoc($result);
	if (!$player)
	{
		$_base->page->add_message("Fant ikke spilleren.", "error");
		redirect::handle();
	}
	
	// ikke i listen?
	if (!isset(login::$info['contacts'][$type][$player['up_id']]))
	{
		$_base->page->add_message('<user id="'.$player['up_id'].'" /> er ikke i listen fra f�r.', "error");
		redirect::handle();
	}
	
	// fjern
	$_base->db->query("DELETE FROM users_contacts WHERE uc_u_id = ".login::$user->id." AND uc_type = $type AND uc_contact_up_id = {$player['up_id']}");
	$_base->db->query("UPDATE users SET u_contacts_update_time = ".time()." WHERE u_id = ".login::$user->id);
	$_base->page->add_message('<user id="'.$player['up_id'].'" /> ble fjernet.', "error");
	redirect::handle();
}

// fjerne flere
if (isset($_POST['del']))
{
	// type
	$type = $_POST['del'] == "contacts" ? 1 : ($_POST['del'] == "blocks" ? 2 : 0);
	if ($type == 0)
	{
		redirect::handle();
	}
	
	// ugyldig?
	if (!isset($_POST['sid']) || $_POST['sid'] != login::$info['ses_id'])
	{
		$_base->page->add_message("Ugyldig.", "error");
		redirect::handle();
	}
	
	// mangler spillere?
	if (!isset($_POST['id']) || !is_array($_POST['id']))
	{
		$_base->page->add_message("Du m� merke noen spillere f�rst.", "error");
		redirect::handle();
	}
	
	$ids = array_unique(array_map("intval", $_POST['id']));
	if (count($ids) == 0)
	{
		$_base->page->add_message("Du m� merke noen spillere f�rst.", "error");
		redirect::handle();
	}
	
	// slett
	$_base->db->query("DELETE FROM users_contacts WHERE uc_u_id = ".login::$user->id." AND uc_type = $type AND uc_contact_up_id IN (".implode(",", $ids).")");
	$ant = $_base->db->affected_rows();
	
	$_base->page->add_message("Du har fjernet $ant spiller".($ant == 1 ? '' : 'e')." fra listen.");
	$_base->db->query("UPDATE users SET u_contacts_update_time = ".time()." WHERE u_id = ".login::$user->id);
	
	redirect::handle();
}



// hent alle kontaktene med sist aktiv tid
$sort_k = new sorts("s_k");
$sort_k->append("asc", "Navn", "up_name");
$sort_k->append("desc", "Navn", "up_name DESC");
$sort_k->append("asc", "Sist aktiv", "up_last_online DESC");
$sort_k->append("desc", "Sist aktiv", "up_last_online");
$sort_k->append("asc", "Lagt til som kontakt", "uc_time");
$sort_k->append("desc", "Lagt til som kontakt", "uc_time DESC");
$sort_k->set_active(getval('s_k'), 0);
$info_k = $sort_k->active();


$sort_b = new sorts("s_b");
$sort_b->append("asc", "Navn", "up_name");
$sort_b->append("desc", "Navn", "up_name DESC");
$sort_b->append("asc", "Sist aktiv", "up_last_online DESC");
$sort_b->append("desc", "Sist aktiv", "up_last_online");
$sort_b->append("asc", "Lagt til som blokkering", "uc_time");
$sort_b->append("desc", "Lagt til som blokkering", "uc_time DESC");
$sort_b->set_active(getval('s_b'), 0);
$info_b = $sort_b->active();


$contacts = array(
	1 => array(),
	2 => array()
);

// hent kontakter
$result = $_base->db->query("SELECT uc_id, uc_contact_up_id, uc_time, uc_info, up_name, up_access_level, up_last_online FROM users_contacts LEFT JOIN users_players ON up_id = uc_contact_up_id WHERE uc_u_id = ".login::$user->id." AND uc_type = 1 ORDER BY {$info_k['params']}");
while ($row = mysql_fetch_assoc($result))
{
	$contacts[1][$row['uc_contact_up_id']] = $row;
}

// hent blokkeringene
$result = $_base->db->query("SELECT uc_id, uc_contact_up_id, uc_time, uc_info, up_name, up_access_level, up_last_online FROM users_contacts LEFT JOIN users_players ON up_id = uc_contact_up_id WHERE uc_u_id = ".login::$user->id." AND uc_type = 2 ORDER BY {$info_b['params']}");
while ($row = mysql_fetch_assoc($result))
{
	$contacts[2][$row['uc_contact_up_id']] = $row;
}

echo '
<h1 id="kontakter">Kontakter</h1>

<p>
	Her er en oversikt over dine kontakter. Disse kontaktene f�r et eget bilde ved siden av spillernavnet n�r spillernavnet blir vist p� siden. For � legge til en kontakt m� du trykke p� kontaktlinken �verst i profilen til vedkommende.
</p>';

if (count($contacts[1]) == 0)
{
	echo '
<p>
	Du har ingen kontakter.
</p>';
}

else
{
	echo '
<form action="" method="post">
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<input type="hidden" name="del" value="contacts" />
	<table class="table spacerfix center">
		<thead>
			<tr>
				<th>Kontakt (<a href="#" class="box_handle_toggle" rel="idk">Merk alle</a>) '.$sort_k->show_link(0, 1).'</th>
				<th>Sist p�logget '.$sort_k->show_link(2, 3).'</th>
				<th>Lagt til '.$sort_k->show_link(4, 5).'</th>
				<th>Informasjon</th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>';
	
	$i = 0;
	foreach ($contacts[1] as $row)
	{
		echo '
			<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
				<td><input type="checkbox" name="id[]" rel="idk" value="'.$row['uc_contact_up_id'].'" />'.game::profile_link($row['uc_contact_up_id'], $row['up_name'], $row['up_access_level']).'</td>
				<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS).'</td>
				<td class="r">'.$_base->date->get($row['uc_time'])->format(date::FORMAT_NOTIME).'</td>
				<td>'.(empty($row['uc_info']) ? '<span class="dark">Ingen info</span>' : game::bb_to_html($row['uc_info'])).'</td>
				<td><a href="kontakter?edit='.$row['uc_id'].'" class="op50"><img src="'.STATIC_LINK.'/other/edit.gif" alt="endre" /></a></td>
			</tr>';
	}
	
	echo '
		</tbody>
	</table>
	<p class="c">
		'.show_sbutton("Fjern", 'onclick="return confirm(\'Sikker p� at du vil fjerne de valgte oppf�ringene?\')"').'
	</p>
</form>';
}


echo '
<h1 id="blokkeringer">Blokkeringsliste</h1>

<p>
	Her er en oversikt over hvem du har blokkert. Disse kontaktene kan ikke sende deg meldinger og f�r et bilde ved siden av spillernavnet n�r spillernavnet blir vist p� siden. For � legge til en blokkering m� du trykke p� blokkeringslinken �verst i profilen til vedkommende.
</p>
<p>
	Begrunnelsen som er satt opp hos vedkommende vil komme opp som begrunnelse n�r en blokkert spiller fors�ker � sende deg en melding og liknende.
</p>';

if (count($contacts[2]) == 0)
{
	echo '
<p>
	Du har ikke blokkert noen spillere.
</p>';
}

else
{
	echo '
<form action="" method="post">
	<input type="hidden" name="del" value="blocks" />
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<table class="table spacerfix center">
		<thead>
			<tr>
				<th>Blokkert (<a href="#" class="box_handle_toggle" rel="idb">Merk alle</a>) '.$sort_b->show_link(0, 1).'</th>
				<th>Sist p�logget '.$sort_b->show_link(2, 3).'</th>
				<th>Lagt til '.$sort_b->show_link(4, 5).'</th>
				<th>Begrunnelse</th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>';
	
	$i = 0;
	foreach ($contacts[2] as $row)
	{
		echo '
			<tr class="box_handle'.(++$i % 2 == 0 ? ' color' : '').'">
				<td><input type="checkbox" name="id[]" rel="idb" value="'.$row['uc_contact_up_id'].'" />'.game::profile_link($row['uc_contact_up_id'], $row['up_name'], $row['up_access_level']).'</td>
				<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS).'</td>
				<td class="r">'.$_base->date->get($row['uc_time'])->format(date::FORMAT_NOTIME).'</td>
				<td>'.(empty($row['uc_info']) ? '<span class="dark">Ingen info</span>' : game::bb_to_html($row['uc_info'])).'</td>
				<td><a href="kontakter?edit='.$row['uc_id'].'" class="op50"><img src="'.STATIC_LINK.'/other/edit.gif" alt="endre" /></a></td>
			</tr>';
	}
	
	echo '
		</tbody>
	</table>
	<p class="c">
		'.show_sbutton("Fjern", 'onclick="return confirm(\'Sikker p� at du vil fjerne de valgte oppf�ringene?\')"').'
	</p>
</form>';
}

$_base->page->load();