<?php

namespace Sunnysideup\UpgradeSilverstripe\Tasks\Helpers;

use Sunnysideup\PHP2CommandLine\PHP2CommandLineSingleton;
use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Composer;

class EasyCodingStandards
{

    protected static bool $hasGlobalInstall = false;
    protected static array $prependCommands = [];

    public static function installIfNotInstalled($mu): void
    {
        if (PHP2CommandLineSingleton::commandExists('sake-lint-rector')) {
            self::$hasGlobalInstall = true;
        } else {
            self::$hasGlobalInstall = false;
            Composer::inst($mu)
                ->RequireDev(
                    'sunnysideup/easy-coding-standards',
                    'dev-master'
                );
            self::$prependCommands[] = 'vendor/bin/';
        }
    }

    public static function prependCommand(): string
    {
        return implode(' ', self::$prependCommands) . ' ';
    }

    public static function isInstalledGlobally($mu): bool
    {
        return self::$hasGlobalInstall;
    }

    public static function removeIfInstalled($mu): void
    {
        if (self::$hasGlobalInstall === false) {
            Composer::inst($mu)
                ->RemoveDev(
                    'sunnysideup/easy-coding-standards',

                );
        }
        self::$hasGlobalInstall = false;
    }
}
