<?php

declare(strict_types=1);

use Sunnysideup\UpgradeSilverstripe\ModuleUpgrader;

$projectRoot = dirname(__DIR__, 2);
if ($projectRoot === false) {
    throw new RuntimeException('Cannot determine project root.');
}

require_once $projectRoot . '/vendor/autoload.php';

ModuleUpgrader::create()
    ->setRecipe('TEST-GITHUB')
    ->setAboveWebRootDirLocation('/var/www/upgrades')
    ->setLogFolderDirLocation('/var/www/upgrades-logs')
    ->setWebRootName('upgradeto6')
    ->setRestartSession(true)
    ->setRunInteractively(true)
    ->setNameOfBranchForBaseCode('main')
    ->setArrayOfModules(
        [
            [
                'VendorName' => 'sunnysideup',
                'VendorNamespace' => 'Sunnysideup',
                'PackageName' => 'delete-all-tables',
                'PackageNamespace' => 'DeleteAllTables',
                'PackageFolderNameForInstall' => 'delete-all-tables',
                'GitLink' => 'git@github.com:sunnysideup/silverstripe-delete-all-tables.git',
                'IsModuleUpgrade' => true,
                'IsOnPackagist' => true,
            ],
        ]
    )
    ->setNameOfTempBranch('tests/github-workflow/temp')
    ->setFrameworkComposerRestraint('^6.0')
    ->run();
