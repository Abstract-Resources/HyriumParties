<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties;

use bitrule\hyrium\parties\service\PartiesService;
use bitrule\hyrium\parties\service\protocol\PartyNetworkDisbandedPacket;
use bitrule\hyrium\parties\service\protocol\PartyNetworkInvitedPacket;
use bitrule\parties\PartiesPlugin;
use bitrule\services\Service;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;

final class HyriumParties {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    /**
     * @param PluginBase $plugin
     */
    public function register(PluginBase $plugin): void {
        PartiesPlugin::getInstance()->setPartyAdapter(new adapter\HyriumPartyAdapter(new PartiesService()));

        Service::getInstance()->registerPacket(new PartyNetworkDisbandedPacket());
        Service::getInstance()->registerPacket(new PartyNetworkInvitedPacket());

        Server::getInstance()->getPluginManager()->registerEvents(new listener\PlayerPreLoginListener(), $plugin);
    }
}