<?php

declare(strict_types=1);

namespace bitrule\hyrium\parties\command;

use abstractplugin\command\BaseCommand;
use bitrule\hyrium\parties\command\argument\CreateArgument;
use bitrule\hyrium\parties\command\argument\DisbandArgument;

final class PartyCommand extends BaseCommand {

    public function __construct() {
        parent::__construct('party', 'Manager your party across our network!', '/party help', ['p']);

        $this->setPermission($this->getPermission());

        $this->registerParent(
            new CreateArgument('create'),
            new DisbandArgument('disband')
        );
    }

    /**
     * @return string|null
     */
    public function getPermission(): ?string {
        return 'parties.command.default';
    }
}