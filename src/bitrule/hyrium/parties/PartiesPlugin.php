<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties;

use bitrule\hyrium\parties\service\protocol\PartyNetworkDisbandedPacket;
use bitrule\hyrium\parties\service\protocol\PartyNetworkInvitedPacket;
use bitrule\services\Service;
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
        Service::getInstance()->registerPacket(new PartyNetworkDisbandedPacket());
        Service::getInstance()->registerPacket(new PartyNetworkInvitedPacket());

        $this->getServer()->getPluginManager()->registerEvents(new listener\PlayerPreLoginListener(), $this);

        $this->getServer()->getCommandMap()->register('parties', new command\PartyCommand());
    }

    public static function prefix(): string {
        return TextFormat::BLUE . TextFormat::BOLD . 'Party ' . TextFormat::GOLD . '» ' . TextFormat::RESET;
    }
}