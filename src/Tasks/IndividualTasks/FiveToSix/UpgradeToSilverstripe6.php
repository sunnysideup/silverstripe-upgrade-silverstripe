<?php

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks\FiveToSix;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\EasyCodingStandards;
use Sunnysideup\PHP2CommandLine\PHP2CommandLineSingleton;
use Sunnysideup\UpgradeSilverstripe\Api\FileSystemFixes;
use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Composer;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * Adds a new branch to your repository that is going to be used for upgrading it.
 */
class UpgradeToSilverstripe6 extends Task
{
    protected $taskStep = 'SS5->SS6';

    protected $composerOptions = '';

    protected $lintingIssuesFileName = 'LINTING_ERRORS';

    public function getTitle()
    {
        return 'Upgrade to Silverstripe 6.';
    }

    public function getDescription()
    {
        return '
            Runs the basic upgrade to Silverstripe 6 code changes.';
    }

    public function runActualTask($params = []): ?string
    {
        $webRoot = $this->mu()->getWebRootDirLocation();
        EasyCodingStandards::installIfNotInstalled($this->mu());


        foreach ($this->mu()->findNameSpaceAndCodeDirs() as $baseNameSpace => $codeDir) {
            $knownIssuesFileName = $codeDir . '/' . $this->lintingIssuesFileName;
            $relativeDir = str_replace($webRoot, '', $codeDir);
            $relativeDir = ltrim($relativeDir, '/');
            FileSystemFixes::inst($this->mu())
                ->removeDirOrFile($knownIssuesFileName);
            $prependCommand = EasyCodingStandards::prependCommand();
            $this->mu()->execMe(
                $webRoot,
                $prependCommand . 'sake-lint-rector  -r ./RectorSS6.php   ' . $relativeDir,
                'Apply easy coding standards to ' . $relativeDir . ' (' . $baseNameSpace . ')',
                false
            );
        }
        EasyCodingStandards::removeIfInstalled($this->mu());
        return null;
    }

    protected function hasCommitAndPush()
    {
        return true;
    }
}
