<?php

// grunnpath
define("ROOT", dirname(dirname(__FILE__)));

// IP-adresse for � hoppe over lockdown status p� hovedserveren
// regex
define("ADMIN_IP", "/(10.8.0.6|127.0.0.1)/");

// er HTTPS aktivert?
define("HTTPS", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "s" : ""));

// last inn innstillinger spesifikt for serveren
$local_settings = dirname(__FILE__) . "/inc.innstillinger_local.php";
if (!file_exists($local_settings))
{
	// fors�k � opprett fil utifra malen
	$template = '<?php


// hvilken versjon dette dokumentet er
// endre denne kun p� foresp�rsel
// brukes til � hindre siden i � kj�re dersom nye innstillinger legges til
// slik at de blir lagt til her f�r siden blir mulig � bruke igjen
// (f�rst etter at nye innstillinger lagt til, skal versjonen settes til det som samsvarer med de nye innstillingene)
$local_settings_version = 1.5;



// linjene som er kommentert med # er eksempler p� andre oppsett



define("DEBUGGING", true);

// hovedserveren?
// settes kun til true p� sm serveren
// dette gj�r at den utelukker enkelte statistikk spesifikt for serveren, aktiverer teststatus av funksjoner osv.
define("MAIN_SERVER", false);

// testversjon p� hovedserveren?
// kun avj�rende hvis MAIN_SERVER er true
// deaktiverer opplasting av bilder p� testserveren, benytter egen test-cache versjon og litt annet
define("TEST_SERVER", false);

// HTTP adresse til static filer
define("STATIC_LINK", "http://www.kofradia.no/static");
#define("STATIC_LINK", "/static");




global $__server;
$__server = array(
	"absolute_path" => "http".HTTPS."://".$_SERVER[\'HTTP_HOST\'],
	"relative_path" => "", // hvis siden ligger i noen undermapper, f.eks. /sm
	"session_prefix" => "sm_",
	"cookie_prefix" => "sm_",
	"cookie_path" => "/",
	"cookie_domain" => "", // eks: ".kofradia.no"
	"https_support" => false, // har vi st�tte for SSL (https)?
	"http_path" => "http://".$_SERVER[\'HTTP_HOST\'], // full HTTP adresse, for videresending fra HTTPS
	"https_path" => false, // full HTTPS adresse, false hvis ikke st�tte for HTTPS, eks: "https://www.kofradia.no"
	"timezone" => "Europe/Oslo"
);
$__server[\'path\'] = $__server[\'absolute_path\'].$__server[\'relative_path\'];




// mappestruktur
// merk at adresse p� windows m� ha to \\.

// HTTP-adresse til lib-mappen (hvor f.eks. MooTools plasseres)
define("LIB_HTTP", $__server[\'path\'] . "/lib");

// HTTP adresse til hvor bildemappen er plassert
define("IMGS_HTTP", $__server[\'path\'] . "/imgs");

// plassering til anti-bot bildene
define("ANTIBOT_FOLDER", ROOT . "/imgs/antibot");

// adresse til mappen hvor alle logger lagres
define("GAMELOG_DIR", dirname(__FILE__) . "/gamelogs");
#define("GAMELOG_DIR", "C:\\\\Users\\\\henrik\\\\Gamelogs");

// knyttet opp mot profilbilder
define("PROFILE_IMAGES_HTTP", IMGS_HTTP . "/profilbilder"); // HTTP-adressen hvor bildene finnes
define("PROFILE_IMAGES_FOLDER", ROOT . "/imgs/profilbilder"); // mappe hvor bildene skal lagres p� disk
#define("PROFILE_IMAGES_FOLDER", "c:\\\\users\\\\henrik\\\\web\\\\static");
define("PROFILE_IMAGES_DEFAULT", "https://kofradia.no/static/other/profilbilde_default.png"); // standard profilbilde

// knyttet opp mot bydeler, kartfiler
define("BYDELER_MAP_FOLDER", ROOT . "/imgs/bydeler"); // adresse til hvor bydelskartene vil bli generert, m� v�re mulig � n� med IMGS_HTTP/bydeler.

// data for crewfiles
define("CREWFILES_DATA_FOLDER", "/home/kofradia/www/kofradia.no/crewfiles/data");
#define("CREWFILES_DATA_FOLDER", "c:\\\\users\\\\henrik\\\\web\\\\crewstuff\\\\f\\\\data");

// mappe hvor vi skal cache for fil-cache (om ikke APC er til stede)
define("CACHE_FILES_DIR", "/tmp");
define("CACHE_FILES_PREFIX", "smcache_");



// databaseinnstillinger
define("DBHOST", "localhost");
#define("DBHOST", ":/var/lib/mysql/mysql.sock"); // linux

// brukernavn til MySQL
define("DBUSER", "brukernavn");

// passord til MySQL
define("DBPASS", "passord");

// MySQL-databasenavn som inneholder dataen
define("DBNAME", "smafia_database");

// mappe hvor arkiv av databaser skal eksporteres
define("DBARCHIVE_DIR", "/home/smafia/dbarchive");


$set = array();

// bruker-ID til SYSTEM-brukeren
$set["system_user_id"] = 16;

// Debug modus - aktiverer enkelte funksjoner for � forenkle debugging/testing
$set["kofradia_debug"] = FALSE;

// facebook app-id og secret
$set["facebook_app_id"] = null;
$set["facebook_app_secret"] = null;


// kommenter eller fjern neste linje ETTER at innstillingene ovenfor er korrigert
die("Innstillingene m� redigeres f�r serveren kan benyttes. Se base/inc.innstillinger_local.php.");';
	
	// fors�k � lagre malen for innstillinger
	if (!file_put_contents($local_settings, $template))
	{
		die("Kunne ikke opprette fil for lokale innstillinger. Fors�ke � opprette base/inc.innstillinger_local.php.");
	}
}

global $local_settings_version, $__server;
require $local_settings;

$__server['spath'] = ($__server['https_path'] ? $__server['https_path'] : $__server['http_path']).$__server['relative_path'];
$__server['rpath'] = $__server['relative_path'];

// kontroller versjonen til de lokale innstillingene
if ($local_settings_version < 1.5)
{
	header("HTTP/1.0 503 Service Unavailiable");
	echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<title>Kofradia</title>
<style>
<!--
body { font-family: tahoma; font-size: 14px; }
h1 { font-size: 23px; }
.hsws { color: #CCCCCC; font-size: 12px; }
.subtitle { font-size: 16px; font-weight: bold; }
-->
</style>
</head>
<body>
<h1>Oppdateringer p� server er n�dvendig</h1>
<p>De lokale innstillingene er ikke oppdatert mot nyeste endringer og m� oppdateres f�r siden kan benyttes.</p>
<p>Se diff i Git for info.</p>
<p class="hsws"><a href="http://hsw.no/">hsw.no</a></p>
</body>
</html>';
	die;
}

// bruker-ID til SYSTEM-brukeren
define("SYSTEM_USER_ID", isset($set['system_user_id']) ? $set['system_user_id'] : 16);

// debug modus
define("KOFRADIA_DEBUG", isset($set['kofradia_debug']) ? $set['kofradia_debug'] : FALSE);

// facebok app-data
define("KOF_FB_APP_ID", isset($set['facebook_app_id']) ? $set['facebook_app_id'] : null);
define("KOF_FB_APP_SECRET", isset($set['facebook_app_secret']) ? $set['facebook_app_secret'] : null);