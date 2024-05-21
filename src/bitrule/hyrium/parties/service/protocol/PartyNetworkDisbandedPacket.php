<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service\protocol;

use bitrule\hyrium\parties\adapter\HyriumPartyAdapter;
use bitrule\parties\MainPlugin;
use bitrule\services\broker\AbstractPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
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

        $partyAdapter = MainPlugin::getInstance()->getPartyAdapter();
        if (!$partyAdapter instanceof HyriumPartyAdapter) {
            throw new RuntimeException('Party adapter is not an instance of HyriumPartyAdapter');
        }

        $party = $partyAdapter->getPartyById($this->id);
        if ($party === null) return;

        $partyAdapter->postDisbandParty($party);
    }
}