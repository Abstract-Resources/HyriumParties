<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service;

use bitrule\hyrium\parties\adapter\HyriumPartyAdapter;
use bitrule\hyrium\parties\service\response\InviteResponse;
use bitrule\hyrium\parties\service\response\InviteState;
use bitrule\parties\object\Party;
use bitrule\parties\PartiesPlugin;
use bitrule\services\response\EmptyResponse;
use bitrule\services\response\PongResponse;
use bitrule\services\Service;
use Closure;
use Exception;
use libasynCurl\Curl;
use pocketmine\utils\InternetRequestResult;
use function array_search;
use function in_array;
use function is_array;
use function json_decode;
use function microtime;

final class PartiesService {

    /**
     * Cache the players than sent an update request
     *
     * @var string[]
     */
    private array $playersPendingRequest = [];

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
                        $onCompletion(HyriumPartyAdapter::wrapParty($body));
                    } catch (Exception $e) {
                        $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'Failed to parse party'));

                        PartiesPlugin::getInstance()->getLogger()->error('Failed to parse party: ' . $e->getMessage());
                    }
                } elseif (!isset($body['message'])) {
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
     * @param string                       $partyId
     * @param string                       $ownershipXuid
     * @param Closure(PongResponse): void  $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function postPartyCreate(string $partyId, string $ownershipXuid, Closure $onCompletion, Closure $onFail): void {
        if (!Service::getInstance()->isRunning()) {
            $onFail(EmptyResponse::create(
                Service::CODE_INTERNAL_SERVER_ERROR,
                'Service is not running'
            ));

            return;
        }

        $initialTimestamp = microtime(true);

        Curl::postRequest(
            Service::URL . '/parties/' . $partyId . '/join/' . $ownershipXuid,
            [],
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
     * @param string                        $targetName
     * @param string                        $partyId
     * @param Closure(InviteResponse): void $onCompletion
     * @param Closure(EmptyResponse): void  $onFail
     */
    public function postPlayerInvite(string $targetName, string $partyId, Closure $onCompletion, Closure $onFail): void {
        if (!Service::getInstance()->isRunning()) {
            $onFail(EmptyResponse::create(
                Service::CODE_INTERNAL_SERVER_ERROR,
                'Service is not running'
            ));

            return;
        }

        Curl::postRequest(
            Service::URL . '/parties/' . $partyId . '/invite/' . $targetName,
            [],
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(
                        Service::CODE_INTERNAL_SERVER_ERROR,
                        'Invite request failed'
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
                    if (!isset($body['xuid'], $body['known_name'])) {
                        $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body response'));
                    } elseif (!isset($body['state'])) {
                        $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No state property'));
                    } else {
                        $onCompletion(new InviteResponse(
                            $body['xuid'],
                            $body['known_name'],
                            InviteState::valueOf($body['state'])
                        ));
                    }
                } elseif (!isset($body['message'])) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body message'));
                } else {
                    $onFail(EmptyResponse::create($code, $body['message']));
                }
            }
        );
    }

    /**
     * @param string  $sourceXuid
     * @param string  $targetName
     * @param Closure(Party): void $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function postPlayerAccept(string $sourceXuid, string $targetName, Closure $onCompletion, Closure $onFail): void {
        if (!Service::getInstance()->isRunning()) {
            $onFail(EmptyResponse::create(
                Service::CODE_INTERNAL_SERVER_ERROR,
                'Service is not running'
            ));

            return;
        }

        Curl::postRequest(
            Service::URL . '/parties/' . $targetName . '/accept/' . $sourceXuid,
            [],
            10,
            Service::defaultHeaders(),
            function (?InternetRequestResult $result) use ($onCompletion, $onFail): void {
                if ($result === null) {
                    $onFail(EmptyResponse::create(
                        Service::CODE_INTERNAL_SERVER_ERROR,
                        'Invite request failed'
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
                        $onCompletion(HyriumPartyAdapter::wrapParty($body));
                    } catch (Exception $e) {
                        $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'Failed to parse party'));

                        PartiesPlugin::getInstance()->getLogger()->error('Failed to parse party: ' . $e->getMessage());
                    }
                } elseif (!isset($body['message'])) {
                    $onFail(EmptyResponse::create(Service::CODE_INTERNAL_SERVER_ERROR, 'No valid body message'));
                } elseif ($code === Service::CODE_NOT_FOUND) {
                    $onFail(EmptyResponse::create(Service::CODE_NOT_FOUND, 'Player not found'));
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
            Service::URL . '/parties/' . $partyId . '/delete',
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