<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\object;

final class Party {

    /**
     * @param string $id
     * @param string $ownership
     * @param array<string, Member>  $members
     */
    public function __construct(
        private readonly string $id,
        private string $ownership,
        private array $members
    ) {}

    /**
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getOwnership(): string {
        return $this->ownership;
    }

    /**
     * @param string $ownership
     */
    public function setOwnership(string $ownership): void {
        $this->ownership = $ownership;
    }

    /**
     * @return array<string, Member>
     */
    public function getMembers(): array {
        return $this->members;
    }

    /**
     * @param string $xuid
     *
     * @return bool
     */
    public function isMember(string $xuid): bool {
        return isset($this->members[$xuid]);
    }

    /**
     * @param Member $member
     */
    public function addMember(Member $member): void {
        $this->members[$member->getXuid()] = $member;
    }

    /**
     * @param string $xuid
     */
    public function removeMember(string $xuid): void {
        unset($this->members[$xuid]);
    }
}