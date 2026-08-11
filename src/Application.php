<?php

declare(strict_types=1);

namespace FullSystem\Install;

use FullSystem\Install\Commands\InstallCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

final class Application extends ConsoleApplication
{
    public const string VERSION = '0.1.0';

    public function __construct()
    {
        parent::__construct('fullsystem/install', self::VERSION);

        $this->addCommand(new InstallCommand);

        $this->setDefaultCommand('install');
    }
}
