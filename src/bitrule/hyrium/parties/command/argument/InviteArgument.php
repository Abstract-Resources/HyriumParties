<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\command\argument;

use abstractplugin\command\Argument;
use abstractplugin\command\PlayerArgumentTrait;
use bitrule\hyrium\parties\object\Member;
use bitrule\hyrium\parties\object\response\PartyInviteResponse;
use bitrule\hyrium\parties\object\Role;
use bitrule\hyrium\parties\PartiesPlugin;
use bitrule\hyrium\parties\service\PartiesService;
use bitrule\services\response\EmptyResponse;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function array_shift;
use function count;
use function is_string;

final class InviteArgument extends Argument {
    use PlayerArgumentTrait;

    /**
     * @param Player $sender
     * @param string $label
     * @param array  $args
     */
    public function onPlayerExecute(Player $sender, string $label, array $args): void {
        if (count($args) === 0) {
            $sender->sendMessage(TextFormat::RED . 'Usage: /' . $label . ' invite <player>');

            return;
        }

        $party = PartiesService::getInstance()->getPartyByPlayer($sender->getXuid());
        if ($party === null) {
            $sender->sendMessage(TextFormat::RED . 'You are not in a party');

            return;
        }

        $name = array_shift($args);
        if (!is_string($name)) {
            $sender->sendMessage(TextFormat::RED . 'Usage: /' . $label . ' invite <player>');

            return;
        }

        $target = Server::getInstance()->getPlayerByPrefix($name);
        if ($target !== null) $name = $target->getName();

        if ($party->isMember($name)) {
            $sender->sendMessage(TextFormat::RED . $name . ' is already in the party');

            return;
        }

        if ($party->hasPendingInvite($name)) {
            $sender->sendMessage(TextFormat::RED . 'You have already invited ' . $name);

            return;
        }

        PartiesService::getInstance()->postPlayerInvite(
            $name,
            $party->getId(),
            function (PartyInviteResponse $inviteResponse) use ($name, $sender, $party): void {
                if ($inviteResponse->getXuid() === null) {
                    $sender->sendMessage(TextFormat::RED . 'Player ' . $name . ' not is online');

                    return;
                }

                if (!$inviteResponse->isInvited()) {
                    $sender->sendMessage(PartiesPlugin::prefix() . TextFormat::GOLD . $inviteResponse->getKnownName() . TextFormat::RED . ' is already in a party');

                    return;
                }

                $party->addMember(new Member(
                    $inviteResponse->getXuid(),
                    $inviteResponse->getKnownName(),
                    Role::MEMBER
                ));

                $sender->sendMessage(PartiesPlugin::prefix() . TextFormat::GREEN . 'You have successfully invited ' . TextFormat::GOLD . $inviteResponse->getKnownName());
            },
            function (EmptyResponse $response) use ($sender): void {
                $sender->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Failed to invite player');
                $sender->sendMessage(TextFormat::RED . $response->getMessage());
            }
        );
    }
}