<?php

/**
 * Rutiner
 * Dette scriptet kalles av cron og utf�rer rutinesjekk
 */

if (!defined("SCRIPT_START"))
{
	require dirname(dirname(__FILE__))."/essentials.php";
	
	// hindre scriptet i � kj�re to ganger
	if (defined("SCHEDULER")) die();
}

define("SCHEDULER", true);

// kj�r rutiner (autoload klassen)
ess::$b->scheduler = new scheduler();