<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;

final class PartiesPlugin extends PluginBase {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    public function onLoad(): void {
        self::setInstance($this);
    }

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents(new listener\PlayerPreLoginListener(), $this);

        $this->getServer()->getCommandMap()->register('parties', new command\PartyCommand());
    }

    public static function prefix(): string {
        return TextFormat::YELLOW . TextFormat::BOLD . 'Parties ' . TextFormat::GOLD . '» ' . TextFormat::RESET;
    }
}