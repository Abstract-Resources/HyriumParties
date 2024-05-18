<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\object;

use InvalidArgumentException;
use RuntimeException;
use function array_filter;
use function array_map;
use function strtolower;

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

        throw new RuntimeException('No owner found');
    }

    /**
     * @return array<string, Member>
     */
    public function getMembers(bool $literal = true): array {
        if ($literal) return array_filter($this->members, fn(Member $member): bool => $member->hasJoined());

        return $this->members;
    }

    /**
     * @param string $xuid
     *
     * @return bool
     */
    public function isMember(string $xuid): bool {
        foreach ($this->members as $member) {
            if (!$member->hasJoined() || $member->getXuid() !== $xuid) continue;

            return true;
        }

        return false;
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
     * @param string $name
     *
     * @return bool
     */
    public function hasPendingInvite(string $name): bool {
        foreach ($this->members as $member) {
            if (strtolower($member->getName()) !== strtolower($name)) continue;
            if ($member->hasJoined()) continue;

            return true;
        }

        return false;
    }

    /**
     * @param array $data
     *
     * @return self
     */
    public static function fromArray(array $data): self {
        if (!isset($data['id'], $data['members'])) {
            throw new InvalidArgumentException('Invalid party data');
        }

        return new self(
            $data['id'],
            $data['open'] ?? false,
            array_map(
                fn(array $member): Member => Member::wrap($member),
                $data['members']
            )
        );
    }
}