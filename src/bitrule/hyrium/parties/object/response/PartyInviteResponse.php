<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\object\response;

final class PartyInviteResponse {

    /**
     * @param string|null $xuid
     * @param string      $knownName
     * @param bool        $invited
     */
    public function __construct(
        private readonly ?string $xuid,
        private readonly string $knownName,
        private readonly bool $invited
    ) {}

    /**
     * @return string|null
     */
    public function getXuid(): ?string {
        return $this->xuid;
    }

    /**
     * @return string
     */
    public function getKnownName(): string {
        return $this->knownName;
    }

    /**
     * @return bool
     */
    public function isInvited(): bool {
        return $this->invited;
    }
}