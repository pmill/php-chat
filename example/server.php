<?php
require_once "../vendor/autoload.php";

$port = 9911;
$server = \pmill\Chat\MultiRoomServer::run($port);
