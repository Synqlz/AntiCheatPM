<?php

declare(strict_types=1);

namespace synqlz\anticheat\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\OfflinePlayer;
use synqlz\anticheat\AntiCheat;
use synqlz\anticheat\log\DetectionLog;
use synqlz\anticheat\session\SessionCache;

class AntiCheatCommand extends Command{
	/** @var String[] */
	private static array $enabled = [];

	public static function getEnabled() : array{
		return self::$enabled;
	}

	public static function isEnabled(string $name) : bool{
		return in_array($name, self::$enabled);
	}

	public static function enable(string $name) : void{
		self::$enabled[] = $name;
	}

	public static function disable(string $name) : void{
		if(in_array($name, self::$enabled)){
			unset(self::$enabled[array_search($name, self::$enabled)]);
		}
	}

	public function __construct(){
		parent::__construct(
			"anticheat",
			"AntiCheat command",
			"/anticheat",
			["ac"]
		);

		$this->setUsage("/anticheat [view <player>]");
		$this->setPermission("synqlz.command.anticheat");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return;
		}

		if(!isset($args[0])){
			if(self::isEnabled($sender->getName())){
				self::disable($sender->getName());
				$sender->sendMessage(AntiCheat::prefix() . "§cYou will no longer see AntiCheat messages.");
			}else{
				self::enable($sender->getName());
				$sender->sendMessage(AntiCheat::prefix() . "You will now see AntiCheat messages.");
			}

			return;
		}

		if(strtolower($args[0]) !== "view" && !isset($args[1])){
			$sender->sendMessage($this->getUsage());
			return;
		}

		$player = $sender->getServer()->getOfflinePlayer($args[1]);

		$session = SessionCache::get($args[1]);

		$message = [AntiCheat::prefix() . "Showing Logs for {$player->getName()}"];
		foreach($session->getLogs() as $name => $logs){
			$message[] = "§r§a" . ucfirst($name) . ", (" . number_format(count($logs)) . " Logs Total)";

			if(count($logs) > 10){
				$message[] = "§r§7Showing last 10 logs";
			}

			$logs = array_splice($logs, 0, 10);
			/** @var DetectionLog $log */
			foreach($logs as $log){
				//show day month year and time
				$time = date("d/m/Y H:i:s", (int)round($log->getTime()));

				$message[] = "§r§7[$time] §r§7{$log->getMessage()}";
			}
		}

		$sender->sendMessage(implode("\n", $message));

		if($player instanceof OfflinePlayer){
			SessionCache::destroy($player->getName());
		}
	}
}