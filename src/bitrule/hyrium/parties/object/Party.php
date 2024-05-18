<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\object;

final class Party {

    /**
     * @param string                $id
     * @param bool                  $open
     * @param array<string, Member> $members
     */
    public function __construct(
        private readonly string $id,
        private bool $open = false,
        private array $members = []
    ) {}

    /**
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isOpen(): bool {
        return $this->open;
    }

    /**
     * @param bool $open
     */
    public function setOpen(bool $open): void {
        $this->open = $open;
    }

    /**
     * @return Member
     */
    public function getOwnership(): Member {
        foreach ($this->members as $member) {
            if ($member->getRole() !== Role::OWNER) continue;

            return $member;
        }

        throw new \RuntimeException('No owner found');
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

    /**
     * @param array $data
     *
     * @return self
     */
    public static function fromArray(array $data): self {

    }
}