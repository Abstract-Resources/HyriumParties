<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service;

use bitrule\hyrium\parties\object\Member;
use bitrule\hyrium\parties\object\Party;
use bitrule\hyrium\parties\PartiesPlugin;
use bitrule\services\response\EmptyResponse;
use bitrule\services\response\PongResponse;
use bitrule\services\Service;
use Closure;
use Exception;
use libasynCurl\Curl;
use pocketmine\utils\InternetRequestResult;
use pocketmine\utils\SingletonTrait;

final class PartiesService {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    /**
     * All parties on this server
     *
     * @var array<string, Party>
     */
    private array $parties = [];
    /**
     * The id of the party that the player is in
     * @var array<string, string>
     */
    private array $playersParties = [];
    /**
     * Cache the players than sent an update request
     *
     * @var string[]
     */
    private array $playersPendingRequest = [];

    /**
     * Get the party by a member
     * First search the party id by the member xuid
     * And then get the party by the party id
     *
     * @param string $xuid
     *
     * @return Party|null
     */
    public function getPartyByPlayer(string $xuid): ?Party {
        $partyId = $this->playersParties[$xuid] ?? null;
        if ($partyId === null) return null;

        return $this->parties[$partyId] ?? null;
    }

    /**
     * @param Party $party
     */
    public function cache(Party $party): void {
        $this->parties[$party->getId()] = $party;
    }

    /**
     * @param Party $party
     */
    public function remove(Party $party): void {
        unset($this->parties[$party->getId()]);

        foreach ($party->getMembers() as $member) {
            unset($this->playersParties[$member->getXuid()]);
        }
    }

    /**
     * @param Member $member
     * @param string $partyId
     */
    public function cacheMember(Member $member, string $partyId): void {
        $this->playersParties[$member->getXuid()] = $partyId;
    }

    /**
     * @param string $xuid
     */
    public function addPlayerRequest(string $xuid): void {
        $this->playersPendingRequest[] = $xuid;
    }

    /**
     * @param string $xuid
     *
     * @return bool
     */
    public function hasPlayerRequest(string $xuid): bool {
        return in_array($xuid, $this->playersPendingRequest, true);
    }

    /**
     * @param string $xuid
     */
    public function removePlayerRequest(string $xuid): void {
        $key = array_search($xuid, $this->playersPendingRequest, true);
        if ($key === false) return;

        unset($this->playersPendingRequest[$key]);
    }

    /**
     * @param string   $xuid
     * @param Closure(Party): void $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function fetch(string $xuid, Closure $onCompletion, Closure $onFail): void {
        if (!Service::getInstance()->isRunning()) {
            $onFail(EmptyResponse::create(
                Service::CODE_INTERNAL_SERVER_ERROR,
                'Service is not running'
            ));

            return;
        }

        $party = $this->getPartyByPlayer($xuid);
        if ($party !== null) {
            $onCompletion($party);

            return;
        }

        Curl::getRequest(
            Service::URL . '/parties?xuid=' . $xuid,
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(
                        Service::CODE_INTERNAL_SERVER_ERROR,
                        'Request failed'
                    ));

                    return;
                }

                $body = json_decode($result->getBody(), true);
                if (!is_array($body)) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body response'));

                    return;
                }

                $code = $result->getCode();
                if ($code === Service::CODE_OK) {
                    try {
                        $onCompletion(Party::fromArray($body));
                    } catch (Exception $e) {
                        $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'Failed to parse party'));

                        PartiesPlugin::getInstance()->getLogger()->error('Failed to parse party: ' . $e->getMessage());
                    }

                    return;
                }

                if (!isset($body['message'])) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body message'));
                } elseif ($code === Service::CODE_NOT_FOUND) {
                    $onFail(EmptyResponse::create(Service::CODE_NOT_FOUND, 'Party not found'));
                } else {
                    $onFail(EmptyResponse::create($code, $body['message']));
                }
            }
        );
    }

    /**
     * @param Party   $party
     * @param Closure(PongResponse): void $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function postUpdate(Party $party, Closure $onCompletion, Closure $onFail): void {
        if (!Service::getInstance()->isRunning()) {
            $onFail(EmptyResponse::create(
                Service::CODE_INTERNAL_SERVER_ERROR,
                'Service is not running'
            ));

            return;
        }

        $initialTimestamp = microtime(true);

        Curl::postRequest(
            Service::URL . '/parties',
            [
                'id' => $party->getId(),
                'open' => $party->isOpen(),
                'members' => array_map(
                    fn(Member $member): array => [
                        'xuid' => $member->getXuid(),
                        'role' => $member->getRole()->name
                    ],
                    $party->getMembers()
                )
            ],
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($initialTimestamp, $onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(
                        Service::CODE_INTERNAL_SERVER_ERROR,
                        'Post request failed'
                    ));

                    return;
                }

                $body = json_decode($result->getBody(), true);
                if (!is_array($body)) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body response'));

                    return;
                }

                $code = $result->getCode();
                if ($code === Service::CODE_OK) {
                    $onCompletion(new PongResponse(
                        $code,
                        $initialTimestamp,
                        microtime(true)
                    ));
                } elseif (!isset($body['message'])) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body message'));
                } else {
                    $onFail(EmptyResponse::create($code, $body['message']));
                }
            }
        );
    }

    /**
     * @param string  $partyId
     * @param Closure(PongResponse): void $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function postDelete(string $partyId, Closure $onCompletion, Closure $onFail): void {
        if (!Service::getInstance()->isRunning()) {
            $onFail(EmptyResponse::create(
                Service::CODE_INTERNAL_SERVER_ERROR,
                'Service is not running'
            ));

            return;
        }

        $initialTimestamp = microtime(true);

        Curl::deleteRequest(
            Service::URL . '/parties/' . $partyId,
            [],
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($initialTimestamp, $onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(
                        Service::CODE_INTERNAL_SERVER_ERROR,
                        'Delete request failed'
                    ));

                    return;
                }

                $body = json_decode($result->getBody(), true);
                if (!is_array($body)) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body response'));

                    return;
                }

                $code = $result->getCode();
                if ($code === Service::CODE_OK) {
                    $onCompletion(new PongResponse(
                        $code,
                        $initialTimestamp,
                        microtime(true)
                    ));
                } elseif (!isset($body['message'])) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body message'));
                } else {
                    $onFail(EmptyResponse::create($code, $body['message']));
                }
            }
        );
    }
}