<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties;

use bitrule\hyrium\parties\service\PartiesService;
use bitrule\hyrium\parties\service\protocol\PartyNetworkDisbandedPacket;
use bitrule\hyrium\parties\service\protocol\PartyNetworkInvitedPacket;
use bitrule\parties\object\impl\MemberImpl;
use bitrule\parties\object\impl\PartyImpl;
use bitrule\parties\object\Party;
use bitrule\parties\PartiesPlugin;
use bitrule\services\Service;
use InvalidArgumentException;
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

    /**
     * @param array $data
     *
     * @return Party
     */
    public static function wrapParty(array $data): Party {
        if (!isset($data['id'])) {
            throw new InvalidArgumentException('Party id is not set');
        }

        if (!isset($data['open'])) {
            throw new InvalidArgumentException('Party open is not set');
        }

        if (!isset($data['members'])) {
            throw new InvalidArgumentException('Party members is not set');
        }

        if (!isset($data['pending_invites'])) {
            throw new InvalidArgumentException('Party pending invites is not set');
        }

        return new PartyImpl(
            $data['id'],
            $data['open'],
            array_map(fn(array $memberData) => MemberImpl::fromArray($memberData), $data['members']),
            $data['pendingInvites'] ?? []
        );
    }
}