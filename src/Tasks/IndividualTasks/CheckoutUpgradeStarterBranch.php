<?php

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Composer;
use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Git;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * This task adds a legacy branch to the git repo of the original to act as a backup/legacy version for
 * holding a version of the module before it was changed
 */
class CheckoutUpgradeStarterBranch extends Task
{
    protected $taskStep = 'ANY';

    protected $composerOptions = '--prefer-source --update-no-dev';

    public function getTitle()
    {
        return 'Checkout the ' . $this->mu()->getNameOfUpgradeStarterBranch() . ' of this module/app.' . "\n" .
            'The name of the branch can be changed by using the following method: setNameOfBranchForBaseCode.' . "\n" .
            'This task may not work if composer is not up-to-date!';
    }

    public function getDescription()
    {
        return '
            Checks out ' . $this->mu()->getNameOfUpgradeStarterBranch() . '
            of project/module using composer for a module or git checkout for a project
            =============================================================================
            NB: this branch may just be created and so composer may fail here,
            simply start again in a few minutes in this case to make it work.';
    }

    /**
     * @param  array  $params not currently used for this task
     */
    public function runActualTask($params = []): ?string
    {
        $this->mu()->setBreakOnAllErrors(true);
        if ($this->mu()->getIsModuleUpgrade()) {
            if ($this->mu()->getIsOnPackagist() !== true) { // this should return true!
                $this->gitClone();
            } else {
                $this->composerRequire();
            }
        } else {
            $this->gitClone();
            $this->mu()->execMe(
                $this->mu()->getWebRootDirLocation(),
                'composer info --self --no-interaction',
                'show information about installed project',
                false
            );
        }
        $this->mu()->setBreakOnAllErrors(false);
        return null;
    }

    protected function gitClone()
    {
        Git::inst($this->mu())
            ->Clone(
                $this->mu()->getWebRootDirLocation(),
                $this->mu()->getGitLink(),
                $this->mu()->getGitRootDir(),
                $this->mu()->getNameOfUpgradeStarterBranch()
            );
    }

    protected function composerRequire()
    {
        $this->mu()->execMe(
            $this->mu()->getWebRootDirLocation(),
            'composer init -s dev -n --no-interaction',
            'Start composer - setting it to dev means that it is more likely
            to install dependencies that do not have tags',
            false
        );
        $packageNameFull = $this->mu()->getVendorName() . '/' . $this->mu()->getPackageName();
        $branchAdjusted = 'dev-' . $this->mu()->getNameOfUpgradeStarterBranch();
        Composer::inst($this->mu())
            ->ClearCache()
            ->Require(
                $packageNameFull,
                $branchAdjusted,
                $this->composerOptions
            );
        $this->mu()->execMe(
            $this->mu()->getWebRootDirLocation(),
            'composer info ' . $this->mu()->getVendorName() . '/' . $this->mu()->getPackageName() . ' --no-interaction',
            'show information about installed package',
            false
        );
    }

    protected function hasCommitAndPush()
    {
        return false;
    }
}
