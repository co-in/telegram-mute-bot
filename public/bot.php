<?php

use TelegramWrapper\TelegramWrapper;

require __DIR__ . '/../vendor/autoload.php';

$telegram = new TelegramWrapper(require __DIR__ . '/../config/app.php');

if (false === $telegram->checkSecretUrl()) {
	die("Forbidden");
}

$telegram->addCommandsPaths([
	dirname(__DIR__) . '/src/Commands/',
]);

$telegram->handle();