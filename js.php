<?php

define("ALLOW_GUEST", true);
require "base.php";
global $_base;

$_base->page->theme_file = "guest";

echo '
<h1>Krever JavaScript st�tte</h1>
<p>Hvis du har kommet hit ved � trykke p� en link, og forventet at noe helt annet skulle skje, er det mest sannsynlig fordi din nettleser ikke har st�tte for JavaScript.</p>
<p>For � kunne utnytte Kofradia fullt ut, m� nettleseren din ha st�tte for JavaScript. Se gjennom hjelpefilene for din nettleser eller last ned en nyere nettleser for � aktivere denne st�tten.</p>
<p>Vi anbefaler bruk av <a href="http://getfirefox.com/">Firefox</a> eller <a href="http://www.opera.com/download/">Opera</a>.</p>';

$_base->page->load();