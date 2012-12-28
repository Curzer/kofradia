<?php

/**
 * Legg til ny forumtr�d
 * 
 * Inndata:
 * - sid
 * - forum_id
 * - title
 * - text
 * - type [optional, forum mod]
 * - locked [optional, forum mod]
 */

require "../../base/ajax.php";
ajax::validate_sid();

// kontroller l�s
ajax::validate_lock(true);

global $_base, $_game;

// hent forum modulen
essentials::load_module("forum");

// kontroller forumkategori og tilgang
$forum = new forum_ajax(postval("forum_id"));
$forum->require_access();

// fors�k � legg til forumtr�den
$type = isset($_POST['type']) && $forum->fmod ? $_POST['type'] : NULL;
$locked = isset($_POST['locked']) && $forum->fmod ? $_POST['locked'] : NULL;
$forum->add_topic(postval("title"), postval("text"), $type, $locked);