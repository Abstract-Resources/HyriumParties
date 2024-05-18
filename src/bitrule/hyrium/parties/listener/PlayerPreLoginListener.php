<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\listener;

use bitrule\hyrium\parties\object\Party;
use bitrule\hyrium\parties\PartiesPlugin;
use bitrule\hyrium\parties\service\PartiesService;
use bitrule\services\response\EmptyResponse;
use bitrule\services\Service;
use InvalidArgumentException;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\utils\TextFormat;

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

        if (PartiesService::getInstance()->getPartyByPlayer($playerInfo->getXuid()) !== null) {
            PartiesPlugin::getInstance()->getLogger()->info(TextFormat::GREEN . 'Party for ' . $playerInfo->getXuid() . ' already loaded!');

            return;
        }

        PartiesService::getInstance()->fetch(
            $playerInfo->getXuid(),
            function (Party $party): void {
                PartiesService::getInstance()->cache($party);

                foreach ($party->getMembers() as $member) {
                    PartiesService::getInstance()->cacheMember($member->getXuid(), $party->getId());
                }
            },
            function (EmptyResponse $response) use ($playerInfo): void {
                if ($response->getMessage() === 'Party not found') return;

                PartiesPlugin::getInstance()->getLogger()->error('Failed to fetch party for ' . $playerInfo->getXuid());
                PartiesPlugin::getInstance()->getLogger()->error($response->getMessage());

                Service::getInstance()->addFailedRequest($playerInfo->getXuid());
            }
        );
    }
}