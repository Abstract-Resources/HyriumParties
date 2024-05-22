<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service\response;

final class InviteResponse {

    /**
     * @param string|null $xuid
     * @param string      $knownName
     * @param InviteState $state
     */
    public function __construct(
        private readonly ?string $xuid,
        private readonly string $knownName,
        private readonly InviteState $state
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
     * @return InviteState
     */
    public function getState(): InviteState {
        return $this->state;
    }

    /**
     * @return bool
     */
    public function isInvited(): bool {
        return $this->invited;
    }
}