<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\command\argument;

use abstractplugin\command\Argument;
use abstractplugin\command\PlayerArgumentTrait;
use bitrule\hyrium\parties\object\Member;
use bitrule\hyrium\parties\object\Party;
use bitrule\hyrium\parties\object\Role;
use bitrule\hyrium\parties\PartiesPlugin;
use bitrule\hyrium\parties\service\PartiesService;
use bitrule\services\response\EmptyResponse;
use bitrule\services\response\PongResponse;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

final class CreateArgument extends Argument {
    use PlayerArgumentTrait;

    /**
     * @param Player $sender
     * @param string $label
     * @param array  $args
     */
    public function onPlayerExecute(Player $sender, string $label, array $args): void {
        if (PartiesService::getInstance()->getPartyByPlayer($sender->getXuid()) !== null) {
            $sender->sendMessage(TextFormat::RED . 'You are already in a party');

            return;
        }

        if (PartiesService::getInstance()->hasPlayerRequest($sender->getXuid())) {
            $sender->sendMessage(TextFormat::RED . 'Please wait for the previous request to be processed');

            return;
        }

        $party = new Party(Uuid::uuid4()->toString());
        $party->addMember($member = new Member(
            $sender->getXuid(),
            $sender->getName(),
            Role::OWNER,
            true
        ));

        PartiesService::getInstance()->addPlayerRequest($sender->getXuid());

        PartiesService::getInstance()->postPlayerJoined(
            $member,
            $party->getId(),
            function (PongResponse $pong) use ($sender, $member, $party): void {
                PartiesService::getInstance()->cache($party);
                PartiesService::getInstance()->cacheMember($member->getXuid(), $party->getId());

                PartiesService::getInstance()->removePlayerRequest($sender->getXuid());

                $sender->sendMessage(PartiesPlugin::prefix() . TextFormat::GOLD . 'You have successfully created a party!');
            },
            function (EmptyResponse $response) use ($sender): void {
                $sender->sendMessage(PartiesPlugin::prefix() . TextFormat::GOLD . 'Failed to create party');
                $sender->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . $response->getMessage());

                PartiesService::getInstance()->removePlayerRequest($sender->getXuid());
            }
        );
    }
}