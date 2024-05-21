<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\listener;

use bitrule\hyrium\parties\adapter\HyriumPartyAdapter;
use bitrule\hyrium\parties\PartiesPlugin;
use bitrule\parties\MainPlugin;
use InvalidArgumentException;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\utils\TextFormat;
use RuntimeException;

final class PlayerPreLoginListener implements Listener {

    /**
     * @param PlayerPreLoginEvent $ev
     *
     * @priority MONITOR
     */
    public function onPlayerPreLoginEvent(PlayerPreLoginEvent $ev): void {
        $playerInfo = $ev->getPlayerInfo();
        if (!$playerInfo instanceof XboxLivePlayerInfo) {
            throw new InvalidArgumentException('PlayerInfo must be XboxLivePlayerInfo');
        }

        $partyAdapter = MainPlugin::getInstance()->getPartyAdapter();
        if (!$partyAdapter instanceof HyriumPartyAdapter) {
            throw new RuntimeException('Party adapter is not an instance of HyriumPartyAdapter');
        }

        if ($partyAdapter->getPartyByPlayer($playerInfo->getXuid()) !== null) {
            PartiesPlugin::getInstance()->getLogger()->info(TextFormat::GREEN . 'Party for ' . $playerInfo->getXuid() . ' already loaded!');
        } else {
            $partyAdapter->loadParty($playerInfo->getXuid());
        }
    }
}