<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service\protocol;

use bitrule\hyrium\parties\adapter\HyriumPartyAdapter;
use bitrule\parties\PartiesPlugin;
use bitrule\services\broker\AbstractPacket;
use bitrule\services\Service;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class PartyNetworkInvitedPacket extends AbstractPacket {

    /** @var string|null */
    private ?string $id = null;
    /** @var string|null */
    private ?string $playerXuid = null;
    /** @var string|null */
    private ?string $playerName = null;

    public function __construct() {
        parent::__construct(2);
    }

    /**
     * @param PacketSerializer $packetSerializer
     */
    public function decode(PacketSerializer $packetSerializer): void {
        $this->id = $packetSerializer->getString();

        $this->playerXuid = $packetSerializer->getString();
        $this->playerName = $packetSerializer->getString();
    }

    /**
     * Method to handle the packet
     */
    public function onHandle(): void {
        if ($this->id === null) {
            throw new RuntimeException('Party id is null');
        }

        if ($this->playerXuid === null) {
            throw new RuntimeException('Player Xuid is null');
        }

        if ($this->playerName === null) {
            throw new RuntimeException('Player name is null');
        }

        $partyAdapter = PartiesPlugin::getInstance()->getPartyAdapter();
        if (!$partyAdapter instanceof HyriumPartyAdapter) {
            throw new RuntimeException('Party adapter is not an instance of HyriumPartyAdapter');
        }

        $party = $partyAdapter->getPartyById($this->id);
        if ($party === null) return;

        // Add the player to the party because this prevents have an outdated party
        // an example is if the ownership joins to a server where the party is already created
        // and the party is outdated, they will be able to invite the player again
        $party->addPendingInvite($this->playerXuid);

        $disbandedMessage = PartiesPlugin::prefix() . TextFormat::YELLOW . $party->getOwnership()->getName() . TextFormat::GOLD . ' has invited ' . TextFormat::YELLOW . $this->playerName . TextFormat::GOLD . ' to the party!';
        foreach ($party->getMembers() as $member) {
            $playerObject = Service::getInstance()->getPlayerObject($member->getXuid());
            if ($playerObject === null || !$playerObject->isOnline()) continue;

            $playerObject->sendMessage($disbandedMessage);
        }
    }
}