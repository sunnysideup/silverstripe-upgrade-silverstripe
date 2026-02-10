<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Git;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * Detect whether the authenticated GitHub user can push directly to the upstream repository.
 * If not, we automatically enable UpgradeAsFork so later steps clone/push via a fork.
 */
class CheckRepositoryPermissions extends Task
{
    protected $taskStep = 'ANY';

    public function getTitle()
    {
        return 'Check Repository Permissions';
    }

    public function getDescription()
    {
        return 'Uses the GitHub CLI (gh) to determine if the authenticated user has admin rights on the
configured repository. When admin rights are missing we flip upgradeAsFork=true so subsequent tasks
operate on a personal fork.';
    }

    public function runActualTask($params = []): ?string
    {
        $gitHelper = Git::inst($this->mu());
        $slug = $gitHelper->resolveGitHubRepoSlug();
        if ($slug === '') {
            $this->mu()->colourPrint('Unable to determine GitHub repository slug; skipping permission check.', 'yellow');
            $this->mu()->setCurrentUserIsAdmin(null);
            return null;
        }

        $isAdmin = $gitHelper->currentUserIsAdmin($slug);
        $this->mu()->setCurrentUserIsAdmin($isAdmin);

        if ($isAdmin) {
            $this->mu()->colourPrint('GitHub user has admin rights on ' . $slug . '. No fork required.', 'light_cyan');
            return null;
        }

        $this->mu()->colourPrint('GitHub user is not an admin on ' . $slug . '. Enabling fork workflow.', 'yellow');
        if (! $this->mu()->getUpgradeAsFork()) {
            $this->mu()->setUpgradeAsFork(true);
        }

        return null;
    }

    protected function hasCommitAndPush()
    {
        return false;
    }
}
