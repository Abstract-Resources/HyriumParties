<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service\protocol;

use bitrule\hyrium\parties\PartiesPlugin;
use bitrule\hyrium\parties\service\PartiesService;
use bitrule\services\broker\AbstractPacket;
use bitrule\services\Service;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class PartyNetworkDisbandedPacket extends AbstractPacket {

    /** @var string|null */
    private ?string $id = null;

    public function __construct() {
        parent::__construct(3);
    }

    /**
     * @param PacketSerializer $packetSerializer
     */
    public function decode(PacketSerializer $packetSerializer): void {
        $this->id = $packetSerializer->getString();
    }

    /**
     * Method to handle the packet
     */
    public function onHandle(): void {
        if ($this->id === null) {
            throw new RuntimeException('Party id is null');
        }

        $party = PartiesService::getInstance()->getPartyById($this->id);
        if ($party === null) return;

        PartiesService::getInstance()->remove($this->id);

        $disbandedMessage = PartiesPlugin::prefix() . TextFormat::YELLOW . $party->getOwnership()->getName() . TextFormat::GOLD . ' has disbanded the party!';
        foreach ($party->getMembers() as $member) {
            PartiesService::getInstance()->removeMember($member->getXuid());

            $playerObject = Service::getInstance()->getPlayerObject($member->getXuid());
            if ($playerObject === null || !$playerObject->isOnline()) continue;

            $playerObject->sendMessage($disbandedMessage);
        }
    }
}