<?php

namespace TelegramWrapper;

use Exception;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\TelegramLog;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class TelegramPatcher extends Telegram {

	protected $commands_objects = [];

	public function getCommandsList() {
		$commands = [];

		foreach ($this->commands_paths as $path) {
			try {
				//Get all "*Command.php" files
				$files = new RegexIterator(
					new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($path)
					),
					'/^.+Command.php$/'
				);

				foreach ($files as $file) {
					$command = $this->sanitizeCommand(substr($file->getFilename(), 0, -11));
					$command_name = mb_strtolower($command);

					if (array_key_exists($command_name, $commands)) {
						continue;
					}

					require_once $file->getPathname() . '';

					$command_obj = $this->getCommandObject($command, $file->getPathname());
					if ($command_obj instanceof Command) {
						$commands[$command_name] = $command_obj;
					}
				}
			} catch (Exception $e) {
				throw new TelegramException('Error getting commands from path: ' . $path, $e);
			}
		}

		return $commands;
	}

	public function getCommandObject($command, $filepath = null) {
		$which = ['System'];
		$this->isAdmin() && $which[] = 'Admin';
		$which[] = 'User';

		foreach ($which as $auth) {

			if ($filepath) {
				$command_namespace = $this->getFileNamespace($filepath) . '\\' . $this->ucfirstUnicode($command) . 'Command';
			} else {
				$command_namespace = __NAMESPACE__ . '\\Commands\\' . $auth . 'Commands\\' . $this->ucfirstUnicode($command) . 'Command';
			}

			if (class_exists($command_namespace)) {
				$command_obj = new $command_namespace($this, $this->update);

				switch ($auth) {
					case 'System':
						if ($command_obj instanceof SystemCommand) {
							return $command_obj;
						}
						break;

					case 'Admin':
						if ($command_obj instanceof AdminCommand) {
							return $command_obj;
						}
						break;

					case 'User':
						if ($command_obj instanceof UserCommand) {
							return $command_obj;
						}
						break;
				}
			}
		}

		return null;
	}

	public function processUpdate(Update $update) {
		$this->update = $update;
		$this->last_update_id = $update->getUpdateId();

		//Load admin commands
		if ($this->isAdmin()) {
			$this->addCommandsPath(TB_BASE_COMMANDS_PATH . '/AdminCommands', false);
		}

		//Make sure we have an up-to-date command list
		//This is necessary to "require" all the necessary command files!
		$this->commands_objects = $this->getCommandsList();

		//If all else fails, it's a generic message.
		$command = 'genericmessage';

		$update_type = $this->update->getUpdateType();
		if ($update_type === 'message') {
			$message = $this->update->getMessage();
			$type = $message->getType();

			// Let's check if the message object has the type field we're looking for...
			$command_tmp = $type === 'command' ? $message->getCommand() : $this->getCommandFromType($type);
			// ...and if a fitting command class is available.

			$command_obj = $this->commands_objects[strtolower($command_tmp)] ?? $this->getCommandObject($command_tmp);

			// Empty usage string denotes a non-executable command.
			// @see https://github.com/php-telegram-bot/core/issues/772#issuecomment-388616072
			if (
				($command_obj === null && $type === 'command')
				|| ($command_obj !== null && $command_obj->getUsage() !== '')
			) {
				$command = $command_tmp;
			}
		} else {
			$command = $this->getCommandFromType($update_type);
		}

		//Make sure we don't try to process update that was already processed
		$last_id = DB::selectTelegramUpdate(1, $this->update->getUpdateId());
		if ($last_id && count($last_id) === 1) {
			TelegramLog::debug('Duplicate update received, processing aborted!');

			return Request::emptyResponse();
		}

		DB::insertRequest($this->update);

		return $this->executeCommand($command);
	}

	public function executeCommand($command) {
		$command = mb_strtolower($command);
		$command_obj = $this->commands_objects[$command] ?? null;

		if (!$command_obj) {
			$command_obj = $this->getCommandObject($command);
		}

		if (!$command_obj || !$command_obj->isEnabled()) {
			//Failsafe in case the Generic command can't be found
			if ($command === 'generic') {
				throw new TelegramException('Generic command missing!');
			}

			//Handle a generic command or non existing one
			$this->last_command_response = $this->executeCommand('generic');
		} else {
			//execute() method is executed after preExecute()
			//This is to prevent executing a DB query without a valid connection
			$this->last_command_response = $command_obj->preExecute();
		}

		return $this->last_command_response;
	}

	private function getFileNamespace($src) {
		$content = file_get_contents($src);
		if (preg_match('#^namespace\s+(.+?);$#sm', $content, $m)) {
			return $m[1];
		}

		return null;
	}
}