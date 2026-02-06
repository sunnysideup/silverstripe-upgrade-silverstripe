<?php

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks\FiveToSix;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Composer;
use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Git;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * This task adds a legacy branch to the git repo of the original to act as a backup/legacy version for
 * holding a version of the module before it was changed
 */
class CheckoutDevMasterAndChangeToMain extends Task
{
    protected $taskStep = 'SS5->SS6';

    /**
     * overruled by NameOfBranchForBaseCode
     *
     * @var string
     */
    protected $oldBranch = 'master';
    protected $newBranch = 'main';

    public function getTitle()
    {
        return 'Checkout the ' . $this->oldBranch . ' branch of this module and changes it to ' . $this->newBranch;
    }

    public function getDescription()
    {
        return '
Checks out ' . $this->oldBranch . ' (customisable using setOldBranch)
of module (not for modules) using git checkout.
And then renames the branch to ' . $this->newBranch . '  (customisable using setNewBranch)
=============================================================================
NB: this branch may just be created and so composer may fail here,
simply start again in a few minutes in this case to make it work.
After that, it replaces master with main as the branch name.';
    }

    /**
     * @param  array  $params not currently used for this task
     */
    public function runActualTask($params = []): ?string
    {
        $alternativeCodeBase = $this->mu()->getNameOfBranchForBaseCode();
        if ($alternativeCodeBase) {
            $this->oldBranch = $alternativeCodeBase;
        }
        $this->mu()->setBreakOnAllErrors(true);
        if ($this->mu()->getIsModuleUpgrade()) {

            Git::inst($this->mu())
                ->renameBranch(
                    $this->mu()->getGitRootDir(),
                    $this->oldBranch,
                    $this->newBranch
                );
            Git::inst($this->mu())
                ->fetchAll(
                    $this->mu()->getGitRootDir()
                );

            Git::inst($this->mu())
                ->checkoutBranch(
                    $this->mu()->getGitRootDir(),
                    $this->newBranch
                );
        } else {
            echo "This only works for modules";
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
                $this->oldBranch
            );
    }

    protected function hasCommitAndPush()
    {
        return false;
    }
}
