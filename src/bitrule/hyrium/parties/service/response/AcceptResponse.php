<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\service\response;

use bitrule\parties\object\Party;

final class AcceptResponse {

    /**
     * @param string|null $xuid
     * @param string|null $knownName
     * @param AcceptState $state
     * @param Party|null  $party
     */
    public function __construct(
        private readonly ?string $xuid,
        private readonly ?string $knownName,
        private readonly AcceptState $state,
        private readonly ?Party $party
    ) {}

    /**
     * @return string|null
     */
    public function getXuid(): ?string {
        return $this->xuid;
    }

    /**
     * @return string|null
     */
    public function getKnownName(): ?string {
        return $this->knownName;
    }

    /**
     * @return AcceptState
     */
    public function getState(): AcceptState {
        return $this->state;
    }

    /**
     * @return Party|null
     */
    public function getParty(): ?Party {
        return $this->party;
    }
}