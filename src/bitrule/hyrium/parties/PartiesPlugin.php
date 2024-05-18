<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

final class PartiesPlugin extends PluginBase {
    use SingletonTrait {
        setInstance as private;
        reset as private;
    }

    public function onLoad(): void {
        self::setInstance($this);
    }
}