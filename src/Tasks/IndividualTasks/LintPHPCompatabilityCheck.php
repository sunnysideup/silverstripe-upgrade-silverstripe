<?php

//requires https://github.com/squizlabs/PHP_CodeSniffer
//requires https://github.com/PHPCompatibility/PHPCompatibility\
//see: https://decentproductivity.com/codesniffer-and-phpcompatibility/'

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use EasyCodingStandards;
use Sunnysideup\PHP2CommandLine\PHP2CommandLineSingleton;
use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Composer;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * Delete the web root directory to allow for a fresh install.
 */
class LintPHPCompatabilityCheck extends Task
{
    protected $taskStep = 'ANY';

    protected $composerOptions = '';

    protected $phpVersion = '8.4';

    public function getTitle()
    {
        return 'PHP Compatibility Check';
    }

    public function getDescription()
    {
        return 'Outputs a file showing errors prevent code from being compatible with php ' . $this->phpVersion;
    }

    public function setPhpVersion(string $phpVersion)
    {
        $this->phpVersion = $phpVersion;

        return $this;
    }

    public function runActualTask($params = []): ?string
    {
        EasyCodingStandards::installIfNotInstalled($this->mu());
        foreach ($this->mu()->getExistingModuleDirLocations() as $moduleDir) {
            $this->mu()->execMe(
                $this->mu()->getWebRootDirLocation(),
                EasyCodingStandards::prependCommand() . 'sake-lint-compat -p ' . $this->phpVersion . ' ' . $moduleDir,
                'Linting check for PHP compatibility in ' . $moduleDir . ' using php ' . $this->phpVersion,
                true
            );
        }
        EasyCodingStandards::removeIfInstalled($this->mu());
        return null;
    }

    protected function hasCommitAndPush()
    {
        return false;
    }
}
