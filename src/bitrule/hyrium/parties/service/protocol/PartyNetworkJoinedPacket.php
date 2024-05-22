<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service\protocol;

use bitrule\hyrium\parties\adapter\HyriumPartyAdapter;
use bitrule\parties\object\impl\MemberImpl;
use bitrule\parties\object\Role;
use bitrule\parties\PartiesPlugin;
use bitrule\services\broker\AbstractPacket;
use bitrule\services\Service;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class PartyNetworkJoinedPacket extends AbstractPacket {

    /** @var string|null */
    private ?string $id = null;
    /** @var string|null */
    private ?string $playerXuid = null;
    /** @var string|null */
    private ?string $playerName = null;

    public function __construct() {
        parent::__construct(4);
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

        // TODO: Change this, first check if the player is connected here, if it is
        // No send the message, because we already sent that message after got a response from the service
        $player = Service::getInstance()->getPlayerObject($this->playerXuid);
        if ($player !== null && $player->isOnline()) return;

        $partyAdapter->postPlayerJoin($this->playerXuid, $this->playerName, $this->id);
    }
}