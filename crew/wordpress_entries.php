<?php

require "../base.php";

// forny data
if (wordpress_entries::update_data() === false)
{
	$_base->page->add_message("Wordpress RSS kunne ikke bli lest.", "error");
}

else
{
	$_base->page->add_message("Wordpress RSS ble lest og data skal v�re oppdatert.");
}

redirect::handle("");