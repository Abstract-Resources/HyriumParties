<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service\response;

enum AcceptState {

    case SUCCESS; // Successfully joined the party
    case NO_ONLINE; // The target player is not online
    case NO_LOADED; // My self is not loaded
    case NO_PARTY; // The target player is not at a party
    case ALREADY_IN_PARTY; // My self is already at a party
    case NO_INVITE; // My self is not invited to the party

    /**
     * @param string $value
     *
     * @return self
     */
    public static function valueOf(string $value): self {
        return match ($value) {
            'SUCCESS' => self::SUCCESS,
            'NO_ONLINE' => self::NO_ONLINE,
            'NO_LOADED' => self::NO_LOADED,
            'NO_PARTY' => self::NO_PARTY,
            'ALREADY_IN_PARTY' => self::ALREADY_IN_PARTY,
            'NO_INVITE' => self::NO_INVITE,
            default => throw new \InvalidArgumentException('Invalid value: ' . $value),
        };
    }
}