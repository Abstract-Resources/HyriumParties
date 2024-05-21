<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\adapter;

use bitrule\hyrium\parties\PartiesPlugin;
use bitrule\hyrium\parties\service\PartiesService;
use bitrule\hyrium\parties\service\response\InviteResponse;
use bitrule\parties\adapter\PartyAdapter;
use bitrule\parties\object\impl\MemberImpl;
use bitrule\parties\object\impl\PartyImpl;
use bitrule\parties\object\Party;
use bitrule\parties\object\Role;
use bitrule\services\response\EmptyResponse;
use bitrule\services\response\PongResponse;
use bitrule\services\Service;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

final class HyriumPartyAdapter extends PartyAdapter {

    /**
     * @param PartiesService $service
     */
    public function __construct(private readonly PartiesService $service) {}

    /**
     * Adapt the method to create a party.
     *
     * @param Player $source
     */
    public function createParty(Player $source): void {
        if ($this->service->hasPlayerRequest($source->getXuid())) {
            $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Please wait for the previous request to be processed');

            return;
        }

        $party = new PartyImpl(Uuid::uuid4()->toString());
        $party->addMember($member = new MemberImpl($source->getXuid(), $source->getName(), Role::OWNER));

        $this->service->addPlayerRequest($source->getXuid());

        $this->service->postPlayerJoined(
            $member,
            $party->getId(),
            function (PongResponse $pong) use ($source, $party): void {
                $this->cache($party);
                $this->cacheMember($source->getXuid(), $party->getId());

                $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GREEN . 'You have successfully created a party!');

                $this->service->removePlayerRequest($source->getXuid());
            },
            function (EmptyResponse $response) use ($source): void {
                $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GOLD . 'Failed to create party');
                $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . $response->getMessage());

                $this->service->removePlayerRequest($source->getXuid());
            }
        );
    }

    /**
     * @param Player $source
     * @param string $playerName
     * @param Party  $party
     */
    public function processInvitePlayer(Player $source, string $playerName, Party $party): void {
        if ($this->service->hasPlayerRequest($source->getXuid())) {
            $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Please wait for the previous request to be processed');

            return;
        }

        $target = Server::getInstance()->getPlayerByPrefix($playerName);
        if ($target !== null) $playerName = $target->getName();

        if ($party->isMember($playerName)) {
            $source->sendMessage(TextFormat::RED . $playerName . ' is already in the party');

            return;
        }

        if ($party->hasPendingInvite($playerName)) {
            $source->sendMessage(TextFormat::RED . 'You have already invited ' . $playerName);

            return;
        }

        $this->service->addPlayerRequest($source->getXuid());

        $this->service->postPlayerInvite(
            $playerName,
            $party->getId(),
            function (InviteResponse $inviteResponse) use ($playerName, $source, $party): void {
                $this->service->removePlayerRequest($source->getXuid());

                if ($inviteResponse->getXuid() === null) {
                    $source->sendMessage(TextFormat::RED . 'Player ' . $playerName . ' not is online');

                    return;
                }

                if (!$inviteResponse->isInvited()) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GOLD . $inviteResponse->getKnownName() . TextFormat::RED . ' is already in a party');

                    return;
                }

                $party->addPendingInvite($inviteResponse->getXuid());

                $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GREEN . 'You have successfully invited ' . TextFormat::GOLD . $inviteResponse->getKnownName());
            },
            function (EmptyResponse $response) use ($source): void {
                $this->service->removePlayerRequest($source->getXuid());

                $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Failed to invite player');
                $source->sendMessage(TextFormat::RED . $response->getMessage());
            }
        );
    }

    /**
     * @param Player $source
     * @param string $playerName
     */
    public function processPlayerAccept(Player $source, string $playerName): void {
        if ($this->service->hasPlayerRequest($source->getXuid())) {
            $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Please wait for the previous request to be processed');

            return;
        }

        $this->service->addPlayerRequest($source->getXuid());

        $this->service->postPlayerAccept(
            sourceXuid: $source->getXuid(),
            targetName: $playerName,
            onCompletion: function (Party $party) use ($source): void {
                $this->service->removePlayerRequest($source->getXuid());

                if (!$party->isMember($source->getXuid())) {
                    $source->sendMessage(TextFormat::RED . $party->getOwnership()->getName() . ' has not invited you to the party');

                    return;
                }

                $this->cache($party);
                $this->cacheMember($source->getXuid(), $party->getId());

                $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GREEN . 'You have successfully joined to the ' . TextFormat::GOLD . $party->getOwnership()->getName() . TextFormat::GREEN . '\'s party!');
            },
            onFail: function (EmptyResponse $response) use ($playerName, $source): void {
                $this->service->removePlayerRequest($source->getXuid());

                if ($response->getMessage() === 'Player not found') {
                    $source->sendMessage(TextFormat::RED . $playerName . ' not is online');
                } elseif ($response->getMessage() === 'Party not found') {
                    $source->sendMessage(TextFormat::RED . $playerName . ' is not in a party');
                } else {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Failed to accept the party invite');
                    $source->sendMessage(TextFormat::RED . $response->getMessage());
                }
            }
        );
    }

    /**
     * @param Player $source
     * @param Player $target
     * @param Party  $party
     */
    public function processKickPlayer(Player $source, Player $target, Party $party): void {
        // TODO: Implement processKickPlayer() method.
    }

    /**
     * @param Player $source
     * @param Party  $party
     */
    public function processLeavePlayer(Player $source, Party $party): void {
        // TODO: Implement processLeavePlayer() method.
    }

    /**
     * Adapt the method to disband a party
     *
     * @param Player $source
     * @param Party  $party
     */
    public function disbandParty(Player $source, Party $party): void {
        if ($this->service->hasPlayerRequest($source->getXuid())) {
            $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Please wait for the previous request to be processed');

            return;
        }

        $this->service->addPlayerRequest($source->getXuid());

        $this->service->postDelete(
            $party->getId(),
            function (PongResponse $response) use ($source): void {
                $this->service->removePlayerRequest($source->getXuid());

                $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GREEN . 'Your party has been successfully disbanded!');
            },
            function (EmptyResponse $response) use ($source): void {
                $this->service->removePlayerRequest($source->getXuid());

                $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Failed to disband the party');
                $source->sendMessage(TextFormat::RED . $response->getMessage());
            }
        );
    }

    /**
     * @param Player $source
     */
    public function onPlayerQuit(Player $source): void {
        $party = $this->getPartyByPlayer($source->getXuid());
        if ($party === null) return;

        $membersOnline = 0;
        foreach ($party->getMembers() as $member) {
            if ($member->getXuid() === $source->getXuid()) continue;

            $playerObject = Service::getInstance()->getPlayerObject($member->getXuid());
            if ($playerObject === null || !$playerObject->isOnline()) continue;

            $membersOnline++;
        }

        if ($membersOnline > 0) return;

        // No members online
        // So we're going to clear the cache and remove the party
        // Only from this server to prevent memory leak or outdated data
        // A subserver cannot delete a party from the global cache
        // Well, it can, but only when the party is disbanded

        PartiesPlugin::getInstance()->getLogger()->info(TextFormat::BLUE . 'The party ' . $party->getOwnership()->getName() . ' has been disbanded because no members are online on this server');

        foreach ($party->getMembers() as $member) {
            $this->clearMember($member->getXuid());
        }

        $this->remove($party->getId());
    }

    /**
     * @param string $sourceXuid
     */
    public function loadParty(string $sourceXuid): void {
        // I think this going to be an issue when many players from the same party join at the same time
        // Because the party is going to be fetched multiple times
        // Maybe the solution is sending a packet of redis to the new server when a player request change his source server
        // So the new server can fetch the party and cache the party before the player joins

        // Another solution is caching the party id fetched the last time
        // So if many members request the party at the same time, no sent any data to the server if his party is the same
        // and the server has the party fetched the last 5 seconds
        // Because the party was requested before his on the same server
        // So the request going to be more fast because the response is only a message, non the party data

        $onCompletion = function (Party $party): void {
            $this->cache($party);

            foreach ($party->getMembers() as $member) {
                $this->cacheMember($member->getXuid(), $party->getId());
            }
        };

        $party = $this->getPartyByPlayer($sourceXuid);
        if ($party !== null) {
            $onCompletion($party);
        } else {
            $this->service->fetch(
                $sourceXuid,
                $onCompletion,
                function (EmptyResponse $response) use ($sourceXuid): void {
                    if ($response->getMessage() === 'Party not found') return;

                    PartiesPlugin::getInstance()->getLogger()->error('Failed to fetch party for ' . $sourceXuid);
                    PartiesPlugin::getInstance()->getLogger()->error($response->getMessage());

                    Service::getInstance()->addFailedRequest($sourceXuid);
                }
            );
        }
    }
}