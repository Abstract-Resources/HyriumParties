<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\object;

final class Member {

    /**
     * @param string $xuid
     * @param string $name
     * @param Role   $role
     * @param bool   $joined
     */
    public function __construct(
        private readonly string $xuid,
        private readonly string $name,
        private Role $role,
        private bool $joined = false
    ) {}

    /**
     * @return string
     */
    public function getXuid(): string {
        return $this->xuid;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return Role
     */
    public function getRole(): Role {
        return $this->role;
    }

    /**
     * @param Role $role
     */
    public function setRole(Role $role): void {
        $this->role = $role;
    }

    /**
     * Lets the system know that the member has joined the party
     *
     * @return bool
     */
    public function hasJoined(): bool {
        return $this->joined;
    }

    /**
     * @param bool $joined
     */
    public function setJoined(bool $joined): void {
        $this->joined = $joined;
    }

    /**
     * @param array $data
     *
     * @return Member
     */
    public static function wrap(array $data): Member {
        if (!isset($data['xuid'], $data['known_name'], $data['role'])) {
            throw new \InvalidArgumentException('Invalid data');
        }

        return new Member(
            $data['xuid'],
            $data['known_name'],
            Role::valueOf($data['role']),
            $data['joined'] ?? false
        );
    }
}