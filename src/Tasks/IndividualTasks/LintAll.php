<?php

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use EasyCodingStandards;
use Sunnysideup\PHP2CommandLine\PHP2CommandLineSingleton;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * This task adds a legacy branch to the git repo of the original to act as a backup/legacy version for
 * holding a version of the module before it was changed
 */
class LintAll extends Task
{
    protected $taskStep = 'ANY';

    public function getTitle()
    {
        return 'Lint all php code.';
    }

    public function getDescription()
    {
        return '
            Goes through all the folders and uses the sake-lint-all function (this will need to be installed).';
    }

    /**
     * [runActualTask description]
     * @param  array  $params not currently used for this task
     */
    public function runActualTask($params = []): ?string
    {
        EasyCodingStandards::installIfNotInstalled($this->mu());
        foreach ($this->mu()->getExistingModuleDirLocations() as $moduleDir) {
            $this->mu()->execMe(
                $this->mu()->getWebRootDirLocation(),
                EasyCodingStandards::prependCommand() . 'sake-lint-all ' . $moduleDir,
                'Linting all PHP files in ' . $moduleDir,
                true
            );
        }
        EasyCodingStandards::removeIfInstalled($this->mu());
        return null;
    }

    protected function hasCommitAndPush()
    {
        return true;
    }

    public function getCommitMessage()
    {
        return $this->commitMessage = 'MNT: linting code';
    }
}
