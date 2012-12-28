<?php

// sjekk alle bankene for forandringer som skal gj�res med overf�ringsgebyret
$result = ess::$b->db->query("SELECT ff_id, ff_name, ff_params FROM ff WHERE ff_type = 3");

while ($row = mysql_fetch_assoc($result))
{
	$params = new params_update($row['ff_params'], "ff", "ff_params", "ff_id = {$row['ff_id']}");
	
	$change = $params->get("bank_overforing_tap_change", 0);
	$current = $params->get("bank_overforing_tap", 0);
	
	// ikke endre?
	if ($change == 0) continue;
	
	$next = $current + $change;
	
	// overstiger maks/min?
	$cancel = false;
	if ($next >= ff::$type_bank['bank_overforing_gebyr_max'] && $change > 0)
	{
		$next = ff::$type_bank['bank_overforing_gebyr_max'];
		$cancel = true;
	}
	elseif ($next <= ff::$type_bank['bank_overforing_gebyr_min'] && $change < 0)
	{
		$next = ff::$type_bank['bank_overforing_gebyr_min'];
		$cancel = true;
	}
	
	// lagre verdier
	$params->update("bank_overforing_tap", $next, false);
	
	// avbryte neste endring?
	if ($cancel)
	{
		$params->update("bank_overforing_tap_change", 0, false);
	}
	
	// lagre
	$params->commit();
	
	// logg
	putlog("NOTICE", "Firma #{$row['ff_id']} ({$row['ff_name']}) - Nytt overf�ringsgebyr: $next ($change)");
	
	// forumlogg
	$action_id = intval(ff::$log['bank_overforing_tap_change'][0]);
	$change = $next - $current;
	$data = ess::$b->db->quote("$current:$change");
	ess::$b->db->query("INSERT INTO ff_log SET ffl_time = ".time().", ffl_ff_id = {$row['ff_id']}, ffl_type = $action_id, ffl_data = $data");
}