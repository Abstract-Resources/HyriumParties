<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service;

use bitrule\hyrium\parties\object\Party;
use bitrule\services\response\EmptyResponse;
use bitrule\services\Service;
use Closure;
use pocketmine\utils\SingletonTrait;

final class PartiesService {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    /**
     * @param string   $xuid
     * @param Closure(Party): void $onCompletion
     * @param Closure(EmptyResponse): void $onFail
     */
    public function fetch(string $xuid, Closure $onCompletion, Closure $onFail): void {
        if (!Service::getInstance()->)
    }
}