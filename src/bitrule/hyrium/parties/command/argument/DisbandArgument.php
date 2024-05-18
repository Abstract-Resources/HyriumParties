<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\command\argument;

use abstractplugin\command\Argument;
use abstractplugin\command\PlayerArgumentTrait;
use bitrule\hyrium\parties\PartiesPlugin;
use bitrule\hyrium\parties\service\PartiesService;
use bitrule\services\response\EmptyResponse;
use bitrule\services\response\PongResponse;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class DisbandArgument extends Argument {
    use PlayerArgumentTrait;

    /**
     * @param Player $sender
     * @param string $label
     * @param array  $args
     */
    public function onPlayerExecute(Player $sender, string $label, array $args): void {
        $party = PartiesService::getInstance()->getPartyByPlayer($sender->getXuid());
        if ($party === null) {
            $sender->sendMessage(TextFormat::RED . 'You are not in a party');

            return;
        }

        $ownership = $party->getOwnership();
        if ($ownership->getXuid() !== $sender->getXuid()) {
            $sender->sendMessage(TextFormat::RED . 'You are not the owner of the party');

            return;
        }

        PartiesService::getInstance()->postDelete(
            $party->getId(),
            function (PongResponse $response) use ($party, $sender): void {
                $sender->sendMessage(PartiesPlugin::prefix() . TextFormat::GREEN . 'Your party has been successfully disbanded!');
            },
            function (EmptyResponse $response) use ($sender): void {
                $sender->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Failed to disband the party');
                $sender->sendMessage(TextFormat::RED . $response->getMessage());
            }
        );
    }
}