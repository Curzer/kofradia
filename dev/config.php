<?php

if (!defined("SCRIPT_START")) die;

// kun p� utviklersiden
if (MAIN_SERVER) redirect::handle("", redirect::ROOT);

ess::$b->page->theme_file = "guest_simple";