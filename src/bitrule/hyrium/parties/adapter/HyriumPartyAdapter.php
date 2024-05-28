<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\adapter;

use bitrule\hyrium\parties\listener\PlayerPreLoginListener;
use bitrule\hyrium\parties\service\PartiesService;
use bitrule\hyrium\parties\service\protocol\PartyNetworkDisbandedPacket;
use bitrule\hyrium\parties\service\protocol\PartyNetworkInvitedPacket;
use bitrule\hyrium\parties\service\protocol\PartyNetworkJoinedPacket;
use bitrule\hyrium\parties\service\response\AcceptResponse;
use bitrule\hyrium\parties\service\response\AcceptState;
use bitrule\hyrium\parties\service\response\InviteResponse;
use bitrule\hyrium\parties\service\response\InviteState;
use bitrule\parties\adapter\PartyAdapter;
use bitrule\parties\object\impl\MemberImpl;
use bitrule\parties\object\impl\PartyImpl;
use bitrule\parties\object\Party;
use bitrule\parties\object\Role;
use bitrule\parties\PartiesPlugin;
use bitrule\services\response\EmptyResponse;
use bitrule\services\response\PongResponse;
use bitrule\services\Service;
use InvalidArgumentException;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

final class HyriumPartyAdapter extends PartyAdapter {

    /**
     * @param PartiesService $service
     */
    public function __construct(private readonly PartiesService $service) {}

    /**
     * @param PluginBase $plugin
     *
     * @return self
     */
    public static function create(PluginBase $plugin): self {
        Service::getInstance()->registerPacket(new PartyNetworkDisbandedPacket());
        Service::getInstance()->registerPacket(new PartyNetworkInvitedPacket());
        Service::getInstance()->registerPacket(new PartyNetworkJoinedPacket());

        Server::getInstance()->getPluginManager()->registerEvents(new PlayerPreLoginListener(), $plugin);

        return new self(new PartiesService());
    }

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
        $party->addMember(new MemberImpl($source->getXuid(), $source->getName(), Role::OWNER));

        $this->service->addPlayerRequest($source->getXuid());

        $this->service->postPartyCreate(
            $party->getId(),
            $source->getXuid(),
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
    public function onPlayerInvite(Player $source, string $playerName, Party $party): void {
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

        $this->service->addPlayerRequest($source->getXuid());

        $this->service->postPlayerInvite(
            $playerName,
            $party->getId(),
            function (InviteResponse $inviteResponse) use ($playerName, $source, $party): void {
                $this->service->removePlayerRequest($source->getXuid());

                if ($inviteResponse->getState() === InviteState::NO_ONLINE || $inviteResponse->getXuid() === null) {
                    $source->sendMessage(TextFormat::RED . 'Player ' . $playerName . ' not is online');
                } elseif ($inviteResponse->getState() === InviteState::NO_PARTY) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'You are not in a party');
                } elseif ($inviteResponse->getState() === InviteState::ALREADY_IN_PARTY) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GOLD . $inviteResponse->getKnownName() . TextFormat::RED . ' is already in a party');
                } elseif ($inviteResponse->getState() === InviteState::ALREADY_INVITED) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GOLD . $inviteResponse->getKnownName() . TextFormat::RED . ' has already been invited');
                } else {
                    $party->addPendingInvite($inviteResponse->getXuid());

                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GREEN . 'You have successfully invited ' . TextFormat::GOLD . $inviteResponse->getKnownName());
                }
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
    public function onPlayerAccept(Player $source, string $playerName): void {
        if ($this->service->hasPlayerRequest($source->getXuid())) {
            $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Please wait for the previous request to be processed');

            return;
        }

        $target = Server::getInstance()->getPlayerByPrefix($playerName);
        if ($target !== null) $playerName = $target->getName();

        $this->service->addPlayerRequest($source->getXuid());

        $this->service->postPlayerAccept(
            sourceXuid: $source->getXuid(),
            targetName: $playerName,
            onCompletion: function (AcceptResponse $acceptResponse) use ($playerName, $source): void {
                $this->service->removePlayerRequest($source->getXuid());

                if ($acceptResponse->getState() === AcceptState::NO_LOADED) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'An error occurred while trying to join the party');
                } elseif ($acceptResponse->getState() === AcceptState::ALREADY_IN_PARTY) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'You are already in a party');
                } elseif ($acceptResponse->getState() === AcceptState::NO_ONLINE || $acceptResponse->getKnownName() === null) {
                    $source->sendMessage(TextFormat::RED . $playerName . ' is not online');
                } elseif ($acceptResponse->getState() === AcceptState::NO_PARTY) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . $acceptResponse->getKnownName() . ' is not in a party');
                } elseif ($acceptResponse->getState() === AcceptState::NO_INVITE) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . $acceptResponse->getKnownName() . ' has not invited you to the party');
                } elseif (($party = $acceptResponse->getParty()) === null) {
                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::RED . 'Failed to join the party');
                } else {
                    if ($this->getPartyById($party->getId()) === null) $this->cache($party);

                    $this->postPlayerJoin($source->getXuid(), $source->getName(), $party->getId());

                    $source->sendMessage(PartiesPlugin::prefix() . TextFormat::GREEN . 'You have successfully joined to the ' . TextFormat::GOLD . $party->getOwnership()->getName() . TextFormat::GREEN . '\'s party!');
                }
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
     * @param string $xuid
     * @param string $name
     * @param string $partyId
     */
    public function postPlayerJoin(string $xuid, string $name, string $partyId): void {
        $party = $this->getPartyById($partyId);
        if ($party === null) return;

        // Add the player to the party because this prevents have an outdated party
        // an example is if the ownership joins to a server where the party is already created
        // and the party is outdated, they will be able to invite the player again
        $party->addMember(new MemberImpl($xuid, $name, Role::MEMBER));
        $this->cacheMember($xuid, $partyId);

        $joinedMessage = PartiesPlugin::prefix() . TextFormat::YELLOW . $name . TextFormat::GOLD . ' has joined to the party!';
        foreach ($party->getMembers() as $member) {
            $playerObject = Service::getInstance()->getPlayerObject($member->getXuid());
            if ($playerObject === null || !$playerObject->isOnline()) continue;

            $playerObject->sendMessage($joinedMessage);
        }
    }

    /**
     * @param Player $source
     * @param Player $target
     * @param Party  $party
     */
    public function onPlayerKick(Player $source, Player $target, Party $party): void {
        // TODO: Implement processKickPlayer() method.
    }

    /**
     * @param Player $source
     * @param Party  $party
     */
    public function onPlayerLeave(Player $source, Party $party): void {
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
            $this->service->lookup(
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

    /**
     * @param array $data
     *
     * @return Party
     */
    public static function wrapParty(array $data): Party {
        if (!isset($data['id'])) {
            throw new InvalidArgumentException('Party id is not set');
        }

        if (!isset($data['open'])) {
            throw new InvalidArgumentException('Party open is not set');
        }

        if (!isset($data['members'])) {
            throw new InvalidArgumentException('Party members is not set');
        }

        if (!isset($data['pending_invites'])) {
            throw new InvalidArgumentException('Party pending invites is not set');
        }

        return new PartyImpl(
            $data['id'],
            $data['open'],
            array_map(fn(array $memberData) => MemberImpl::fromArray($memberData), $data['members']),
            $data['pending_invites']
        );
    }
}