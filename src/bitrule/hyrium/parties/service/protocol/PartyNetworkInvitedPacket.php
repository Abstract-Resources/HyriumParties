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

final class PartyNetworkInvitedPacket extends AbstractPacket {

    /** @var string|null */
    private ?string $id = null;
    /** @var string|null */
    private ?string $playerName;

    public function __construct() {
        parent::__construct(2);
    }

    /**
     * @param PacketSerializer $packetSerializer
     */
    public function decode(PacketSerializer $packetSerializer): void {
        $this->id = $packetSerializer->getString();
        $this->playerName = $packetSerializer->getString();
    }

    /**
     * Method to handle the packet
     */
    public function onHandle(): void {
        if ($this->id === null) {
            throw new RuntimeException('Party id is null');
        }

        if ($this->playerName === null) {
            throw new RuntimeException('Player name is null');
        }

        $party = PartiesService::getInstance()->getPartyById($this->id);
        if ($party === null) return;

        $disbandedMessage = PartiesPlugin::prefix() . TextFormat::YELLOW . $party->getOwnership()->getName() . TextFormat::GOLD . ' has invited ' . TextFormat::YELLOW . $this->playerName . TextFormat::GOLD . ' to the party!';
        foreach ($party->getMembers() as $member) {
            $playerObject = Service::getInstance()->getPlayerObject($member->getXuid());
            if ($playerObject === null || !$playerObject->isOnline()) continue;

            $playerObject->sendMessage($disbandedMessage);
        }
    }
}