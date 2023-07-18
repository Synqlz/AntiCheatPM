<?php

declare(strict_types=1);

namespace synqlz\anticheat;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use synqlz\anticheat\command\AntiCheatCommand;
use synqlz\anticheat\listener\EventListener;
use synqlz\anticheat\session\SessionCache;

class AntiCheat extends PluginBase{
	public const PLUGIN_NAME = "AntiCheat";

	public function onEnable() : void{
		$this->registerCommands();
		$this->registerListeners();
	}

	public function onDisable() : void{
		SessionCache::destroyAll();
	}

	private function registerCommands() : void{
		$this->getServer()->getCommandMap()->registerAll(self::PLUGIN_NAME, [
			new AntiCheatCommand()
		]);
	}

	private function registerListeners() : void{
		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
	}


	public static function getInstance() : Plugin{
		return Server::getInstance()->getPluginManager()->getPlugin(self::PLUGIN_NAME);
	}

	public static function prefix() : string{
		return "§f[§l§bAntiCheat§r§f] ";
	}
}