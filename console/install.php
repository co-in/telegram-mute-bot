<?php

/**@var TelegramWrapper $telegram */

use TelegramWrapper\TelegramWrapper;

require __DIR__ . '/../vendor/autoload.php';

$telegram = new TelegramWrapper(require __DIR__ . '/../config/app.php');

if ($argc < 2 || false === is_string($argv[1])) {
	die("Invalid Hook URL\n");
}

$response = $telegram->install($argv[1]);

if (false === $response->isOk()) {
	die($response->getDescription() . "\n");
}