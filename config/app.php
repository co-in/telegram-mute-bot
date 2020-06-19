<?php

return array_replace([
	//Заполняется в app_local.php
	'secretUrl' => null,
	'botToken' => null,
	'botUsername' => null,

	'timeout' => 2592000, //30дней * 24часа * 60минут * 60секунд
	'permissions' => [
		'can_send_messages' => false,
		'can_send_media_messages' => false,
		'can_send_polls' => false,
		'can_send_other_messages' => false,
		'can_add_web_page_previews' => false,
		'can_change_info' => false,
		'can_invite_users' => false,
		'can_pin_messages' => false,
	],
], require __DIR__ . '/app_local.php');