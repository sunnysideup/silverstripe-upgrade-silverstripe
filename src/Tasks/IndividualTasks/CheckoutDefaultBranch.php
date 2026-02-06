<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Composer;
use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Git;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * This task checks out the default branch (remote HEAD) of the repo,
 * unless a branch/tag is explicitly provided via $branchOrTagToUse (or NameOfBranchForBaseCode).
 */
class CheckoutDefaultBranch extends Task
{
    protected $taskStep = 'ANY';

    /**
     * If set, this overrides default-branch detection.
     *
     * @var string
     */
    protected string $branchOrTagToUse = '';

    /**
     * Fallback list if we cannot detect remote HEAD.
     *
     * @var array
     */
    protected array $branchesToTryInThisOrder = ['develop', 'main', 'master'];

    protected $clearCache = true;

    protected $composerOptions = '--prefer-source --update-no-dev';

    public function getTitle()
    {
        $label = $this->branchOrTagToUse ? $this->branchOrTagToUse : 'default branch';

        return 'Checkout the ' . $label . ' of this module';
    }

    public function getDescription()
    {
        return '
Checks out the default branch (remote HEAD) of the project/module.
You can override this by setting branchOrTagToUse (or using setNameOfBranchForBaseCode).
=============================================================================
NB: this branch may just be created and so composer may fail here,
simply start again in a few minutes in this case to make it work.';
    }

    /**
     * @param  array  $params not currently used for this task
     */
    public function runActualTask($params = []): ?string
    {
        // 1. explicit override on the task wins
        // 2. NameOfBranchForBaseCode override wins next
        // 3. else: detect remote default branch (HEAD), else fallback list, else empty (= git default)
        $this->branchOrTagToUse =
            $this->branchOrTagToUse ?:
            $this->mu()->getNameOfBranchForBaseCode() ?:
            Git::inst($this->mu())->resolveBranchOrTagToUse($this->branchesToTryInThisOrder);

        $this->mu()->setBreakOnAllErrors(true);

        if ($this->mu()->getIsModuleUpgrade()) {
            if ($this->mu()->getIsOnPackagist() !== true) {
                $this->gitClone();
            } else {
                $this->mu()->execMe(
                    $this->mu()->getWebRootDirLocation(),
                    'composer init -s dev -n --no-interaction',
                    'Start composer - setting it to dev means that it is more likely to install dependencies that do not have tags',
                    false
                );

                $constraint = $this->convertBranchOrTagToComposerConstraint($this->branchOrTagToUse);

                Composer::inst($this->mu())
                    ->ClearCache($this->clearCache)
                    ->Require(
                        $this->mu()->getVendorName() . '/' . $this->mu()->getPackageName(),
                        $constraint,
                        $this->composerOptions
                    );

                $this->mu()->execMe(
                    $this->mu()->getWebRootDirLocation(),
                    'composer info ' . $this->mu()->getVendorName() . '/' . $this->mu()->getPackageName() . ' --no-interaction',
                    'show information about installed package',
                    false
                );
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
        // If we have a branch/tag, keep using the helper (as before).
        if ($this->branchOrTagToUse) {
            Git::inst($this->mu())
                ->Clone(
                    $this->mu()->getWebRootDirLocation(),
                    $this->mu()->getGitLink(),
                    $this->mu()->getGitRootDir(),
                    $this->branchOrTagToUse
                );

            return;
        }

        // Otherwise clone without specifying a branch => git checks out remote default branch.
        $gitRootDir = (string) $this->mu()->getGitRootDir();
        $gitLink = (string) $this->mu()->getGitLink();
        $webRoot = (string) $this->mu()->getWebRootDirLocation();

        if ($gitRootDir === '' || $gitRootDir === '/' || strlen($gitRootDir) < 3) {
            $this->mu()->execMe($webRoot, 'false', 'Refusing to clone into an unsafe path: ' . $gitRootDir, true);
            return;
        }

        $this->mu()->execMe(
            $webRoot,
            'rm -rf ' . escapeshellarg($gitRootDir),
            'remove existing clone before cloning default branch',
            false
        );

        $this->mu()->execMe(
            $webRoot,
            'git clone ' . escapeshellarg($gitLink) . ' ' . escapeshellarg($gitRootDir),
            'clone repository (default branch)',
            false
        );
    }

    protected function hasCommitAndPush()
    {
        return false;
    }



    protected function convertBranchOrTagToComposerConstraint(string $branchOrTag): string
    {
        $branchOrTag = trim($branchOrTag);

        if ($branchOrTag === '') {
            return '@dev';
        }

        // if user already passed a composer-style dev constraint, keep it
        if (str_starts_with($branchOrTag, 'dev-')) {
            return $branchOrTag;
        }

        // treat anything starting with a digit (or v+digit) as a tag/version
        if (preg_match('/^v?\d/', $branchOrTag) === 1) {
            return $branchOrTag;
        }

        return 'dev-' . $branchOrTag;
    }
}
