<?php
require_once "../vendor/autoload.php";
require_once "ExampleServer.php";

$port = 9911;
$server = new \pmill\Chat\BasicMultiRoomServer;

\pmill\Chat\MultiRoomServer::run($server, $port);
