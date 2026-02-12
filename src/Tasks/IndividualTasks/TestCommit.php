<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

class TestCommit extends Task
{
    protected $taskStep = 'ANY';

    public function getTitle()
    {
        return 'Create a test commit';
    }

    public function getDescription()
    {
        return 'Creates a test commit on the current branch to verify the upgrade workflow
can create commits. This is useful for testing the GitHub fork/PR workflow.';
    }

    public function runActualTask($params = []): ?string
    {
        $gitRootDir = (string) $this->mu()->getGitRootDir();
        if ($gitRootDir === '' || ! is_dir($gitRootDir . '/.git')) {
            $this->mu()->colourPrint('No git repository found; skipping test commit.', 'yellow');
            return null;
        }

        $testFile = $gitRootDir . '/test-upgrade.md';
        $content = "# Test Upgrade Commit\n\n";
        $content .= "Created: " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "This file is used to verify that the upgrade workflow can create commits.\n";

        if (file_put_contents($testFile, $content) === false) {
            return 'Failed to create test-upgrade.md file.';
        }

        $this->mu()->execMe(
            $gitRootDir,
            'git add test-upgrade.md',
            'stage test-upgrade.md',
            false
        );

        $this->mu()->execMe(
            $gitRootDir,
            'git commit -m "Test commit for upgrade verification"',
            'commit test change',
            false
        );

        $this->mu()->colourPrint('Test commit created: test-upgrade.md', 'green');

        return null;
    }

    protected function hasCommitAndPush()
    {
        return false;
    }
}
