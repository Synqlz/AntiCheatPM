<?php

declare(strict_types=1);

namespace synqlz\anticheat\task;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use synqlz\anticheat\AntiCheat;

class KickTask extends Task{
	public static function kick(Player $player, string $reason, int $ticks) : void{
		AntiCheat::getInstance()->getScheduler()->scheduleDelayedTask(new self($player, $reason), $ticks);
	}

	public function __construct(protected Player $player, protected string $reason){
	}

	public function onRun() : void{
		$this->player->kick($this->reason);
	}
}