<?php

namespace TelegramWrapper;

use Longman\TelegramBot\Entities\ServerResponse;

class TelegramWrapper extends TelegramPatcher {

	private $secretUrl;

	private $timeout;

	private $permissions;

	public function __construct(array $config) {
		$this->secretUrl = $config['secretUrl'];
		$this->timeout = $config['timeout'];
		$this->permissions = $config['permissions'];

		parent::__construct($config['botToken'], $config['botUsername']);
	}

	public function install(string $url): ServerResponse {
		$url = rtrim($url, '/') . '/';

		return $this->setWebhook("{$url}bot.php?key={$this->secretUrl}", []);
	}

	public function checkSecretUrl(): bool {
		return true === array_key_exists('key', $_GET) && $_GET['key'] === $this->secretUrl;
	}

	public function getTimeout(): int {
		return $this->timeout;
	}

	public function getPermissions(): array {
		return $this->permissions;
	}
}