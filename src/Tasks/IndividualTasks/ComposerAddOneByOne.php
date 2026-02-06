<?php

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\ComposerJsonFixes;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;



class ComposerAddOneByOne extends Task
{
    protected $taskStep = 'ANY';

    protected $alwaysKeepArray = [
        'silverstripe/recipe-cms',
    ];

    protected $appendixToKey = '-tmp';

    public function getTitle()
    {
        if ($this->mu()->getIsModuleUpgrade()) {
            return 'For module upgrades, this is not being used right now.';
        }
        return 'Check what composer requirements can be added.';
    }

    public function getDescription()
    {
        return 'First it moves the current requirements into a temporary, different name in the composer.json file.
        Then it goes through all the composer requirements and adds them back one by one...
        If it works great - if not - it removes them from the main requirements and keeps them in the temporary section.
        Finally, it runs a composer update to ensure everything is in order again.';
    }

    /**
     * @param array $params
     */
    public function runActualTask($params = []): ?string
    {
        $this->mu()->setBreakOnAllErrors(true);
        if ($this->mu()->getIsModuleUpgrade()) {
            return null;
        }
        $this->moveToTmpVar();
        $this->testEachRequirement();

        $this->mu()->execMe(
            $this->mu()->getGitRootDir(),
            'composer update -vvv --no-interaction',
            'run composer update',
            false
        );
        $this->mu()->setBreakOnAllErrors(false);

        return null;
    }

    protected function moveToTmpVar()
    {
        $composerData = ComposerJsonFixes::inst($this->mu())->getJSON(
            $this->mu()->getGitRootDir()
        );
        foreach (['require', 'require-dev'] as $section) {
            $tmpSection = $section . $this->appendixToKey;
            // move all
            if (! empty($composerData[$section])) {
                $composerData[$tmpSection] = $composerData[$section];
                unset($composerData[$section]);
            }
            // move the keeps back!
            foreach ($this->alwaysKeepArray as $package) {
                if (isset($composerData[$tmpSection][$package])) {
                    $composerData[$section][$package] = $composerData[$tmpSection][$package];
                    unset($composerData[$tmpSection][$package]);
                }
            }
        }
        ComposerJsonFixes::inst($this->mu())->setJSON(
            $this->mu()->getGitRootDir(),
            $composerData
        );
    }

    protected function testEachRequirement()
    {
        $composerData = ComposerJsonFixes::inst($this->mu())->getJSON(
            $this->mu()->getGitRootDir()
        );
        foreach (['require', 'require-dev'] as $section) {
            $tmpSection = $section . $this->appendixToKey;
            if (! empty($composerData[$tmpSection])) {
                foreach ($composerData[$tmpSection] as $package => $version) {
                    $this->mu()->colourPrint('trying to add ' . $package . ':' . $version, 'yellow', 1);

                    // Attempt to require the package
                    $output = '';
                    exec("composer require $package:$version --no-update", $output, $returnVar);
                    if ($returnVar !== 0) {
                        $this->mu()->colourPrint("$package:$version could not be added. Skipping...", 'red', 1);
                        $this->updateComposerJson($section, $package, $version, 'remove');
                        // Skip adding to suggest or removing since it's being processed dynamically
                    } else {
                        // If require was successful, update composer
                        $output = '';
                        exec('composer update', $output, $updateReturnVar);
                        if ($updateReturnVar !== 0) {
                            // If update fails, revert the require
                            $this->mu()->colourPrint("Update failed after requiring $package. Reverting...", 'red', 1);
                            $this->updateComposerJson($section, $package, $version, 'remove');
                        } else {
                            $this->updateComposerJson($section, $package, $version, 'add');
                        }
                    }
                }
            }
        }
    }

    /**
     * Update composer.json to add, remove, or suggest a package.
     */
    protected function updateComposerJson(string $section, string $package, string $version, string $action, string $message = ''): void
    {
        $composerData = ComposerJsonFixes::inst($this->mu())->getJSON(
            $this->mu()->getGitRootDir()
        );
        switch ($action) {
            case 'remove':
                $this->mu()->colourPrint('removing ' . $package . ':' . $version, 'red', 1);
                unset($composerData[$section][$package]);
                $composerData[$section . '-tmp'][$package] = $version;
                break;
            case 'add':
                $this->mu()->colourPrint('adding ' . $package . ':' . $version, 'green', 1);
                $composerData[$section][$package] = $version;
                unset($composerData[$section . '-tmp'][$package]);
                break;
        }
        ComposerJsonFixes::inst($this->mu())->setJSON(
            $this->mu()->getGitRootDir(),
            $composerData
        );
    }

    protected function hasCommitAndPush()
    {
        return false;
    }
}
