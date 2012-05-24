<?php

require dirname(__FILE__) . "/colours.class.php";
require dirname(__FILE__) . "/helpers.php";
require dirname(__FILE__) . "/whippet.class.php";

$whippet = new Whippet();

// Run returns some code that needs to be executed at global scope
$code = $whippet->run();

eval($code);

// The code eval'd above might call die() or exit(), so we won't necessarily end up here.
// If you want to run something that executes when WordPress is finished, add a shutdown
// filter for it, or add your code to Whippet::wps_filter_shutdown
