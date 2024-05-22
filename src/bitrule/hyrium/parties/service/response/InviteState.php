<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service\response;

enum InviteState {
    case SUCCESS;
    case NO_INVITED;
    case NO_ONLINE;
    case NO_PARTY;
    case ALREADY_IN_PARTY;

    /**
     * @param string $name
     *
     * @return self
     */
    public static function valueOf(string $name): self {
        return match (strtoupper($name)) {
            'SUCCESS' => self::SUCCESS,
            'NO_INVITED' => self::NO_INVITED,
            'NO_ONLINE' => self::NO_ONLINE,
            'NO_PARTY' => self::NO_PARTY,
            'ALREADY_IN_PARTY' => self::ALREADY_IN_PARTY,
            default => throw new \InvalidArgumentException('Invalid InviteState name: ' . $name),
        };
    }
}
