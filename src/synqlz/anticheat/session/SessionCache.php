<?php

declare(strict_types=1);

namespace synqlz\anticheat\session;

use pocketmine\player\Player;

class SessionCache{
	/** @var array<string, Session> */
	private static array $sessions = [];

	public static function destroyAll(): void {
		foreach(self::$sessions as $username => $session){
			self::destroy($username);
		}
	}

	public static function get(Player|string $username) : Session{
		$username = $username instanceof Player ? $username->getName() : $username;
		return self::$sessions[strtolower($username)] ??= new Session($username);
	}

	public static function destroy(Player|string $username, bool $save = true) : void{
		$username = strtolower($username instanceof Player ? $username->getName() : $username);
		if(isset(self::$sessions[$username])){
			if($save){
				self::$sessions[$username]->save();
			}

			unset(self::$sessions[$username]);
		}
	}
}