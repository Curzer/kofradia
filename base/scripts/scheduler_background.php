<?php

/**
 * Rutiner
 * Dette scriptet kj�res manuelt og utf�rer rutiner kontinuerlig uten behov for cron
 */

if (!defined("SCRIPT_START"))
{
	require dirname(dirname(__FILE__))."/essentials.php";
	
	// hindre scriptet i � kj�re to ganger
	if (defined("SCHEDULER")) die();
}

set_time_limit(0);

define("SCHEDULER", true);
define("SCHEDULER_REPEATING", true);
sess_start();

echo "Utf�rer rutine regelmessig.\n";

// kj�r rutiner (autoload klassen)
ess::$b->scheduler = new scheduler();

// utf�r rutiner regelmessig
while (true)
{
	// finn ut n�r neste rutine skal utf�res
	$result = ess::$b->db->query("
		SELECT GREATEST(s_next, s_expire) next
		FROM scheduler
		WHERE s_active = 1
		ORDER BY next
		LIMIT 1");
	$row = mysql_fetch_assoc($result);
	$next = false;
	if ($row)
	{
		$next = $row['next'];
	}
	
	$t = time();
	$s = ess::$b->date->get($t)->format("s");
	$max = $t + 60 - $s;
	
	if (!$next || $next > $max) $next = $max;
	
	printf("Neste: %s\n", ess::$b->date->get($next)->format(date::FORMAT_SEC));
	
	// sov
	$sleep = max(0.1, $next - microtime(true));
	putlog("LOG", sprintf("Venter %.2f sekunder til neste.\n", $sleep));
	usleep($sleep * 1000000);
	
	ess::$b->scheduler->__construct();
}