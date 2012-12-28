<?php

/**
 * Forh�ndsvisning av forumtr�d
 * 
 * Inndata:
 * - topic_id [optional]
 * - text
 */

require "../../base/ajax.php";
ajax::require_user();

// kontroller l�s
ajax::validate_lock(true);

global $_base, $_game;

// sett opp tekst
$text = postval("text");
if (empty($text)) $text = "Mangler innhold.";

// forh�ndsviser vi en redigert forumtr�d?
if (isset($_POST['topic_id']))
{
	// hent forum modulen
	essentials::load_module("forum");
	
	// hent forumtr�den
	$topic = new forum_topic_ajax($_POST['topic_id']);
	
	// sett opp data
	$data = $topic->extended_info();
	$data['ft_text'] = $text;
	$data['ft_last_edit'] = time();
	$data['ft_last_edit_up_id'] = login::$user->player->id;
}

// forh�ndsviser ny forumtr�d (bruk egen brukerdata)
else
{
	// sett opp data
	$data = array(
		"ft_text" => $text
	);
}

ajax::html(parse_html(forum::template_topic_preview($data)));