<?php

require "../base.php";

ess::$b->page->add_title("Utviklerverkt�y");

echo '
<h1>Utviklerverkt�y</h1>
<p>Her finner du verkt�y for � administrere utviklersiden.</p>
<ul class="spacer">
	<li><a href="set_pass">Endre passord p� en bruker</a></li>
	<li><a href="login">Logg inn som en annen bruker</a></li>
	<li><a href="replace_db">Erstatt databasen med ny versjon</a></li>
</ul>
<p>Opprett gjerne nye scripts hvis det er handlinger som man f�ler kan v�re n�dvendige � utf�re p� utviklersiden.</p>';

ess::$b->page->load();