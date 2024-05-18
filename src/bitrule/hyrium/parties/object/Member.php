<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\object;

final class Member {

    /**
     * @param string $xuid
     * @param string $name
     * @param Role   $role
     */
    public function __construct(
        private readonly string $xuid,
        private readonly string $name,
        private Role $role
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
     * @param array $data
     *
     * @return Member
     */
    public static function wrap(array $data): Member {
        if (!isset($data['xuid'], $data['known_name'], $data['role'])) {
            throw new \InvalidArgumentException('Invalid data');
        }

        return new Member($data['xuid'], $data['known_name'], Role::valueOf($data['role']));
    }
}