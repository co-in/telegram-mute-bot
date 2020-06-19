<?php

namespace Commands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use TelegramWrapper\TelegramWrapper;

class NewchatmembersCommand extends SystemCommand {

	protected $usage = 'Command usage';

	public function execute(): ServerResponse {
		$message = $this->getMessage();
		$members = $message->getNewChatMembers();
		$response = $message;
		/**@var TelegramWrapper $telegram */
		$telegram = $this->telegram;

		foreach ($members as $member) {
			if ($member->getIsBot()) {
				return parent::execute();
			}

			$response = Request::restrictChatMember([
				'chat_id' => $message->getChat()->getId(),
				'user_id' => $member->getId(),
				'permissions' => $telegram->getPermissions(),
				'until_date' => $message->getDate() + $telegram->getTimeout(),
			]);
		}

		return $response;
	}
}