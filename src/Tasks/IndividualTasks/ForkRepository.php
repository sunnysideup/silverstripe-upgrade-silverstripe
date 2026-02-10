<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Git;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * Ensure the upgrade operates on a writable fork instead of the upstream repository.
 */
class ForkRepository extends Task
{
    protected $taskStep = 'ANY';

    public function getTitle()
    {
        $target = trim($this->mu()->getVendorName() . '/' . $this->mu()->getPackageName(), '/');
        if ($target === '') {
            $target = 'selected repository';
        }

        return 'Fork ' . $target . ' for this upgrade';
    }

    public function getDescription()
    {
        return 'This step only executes when upgradeAsFork=true. It ensures the current
upgrade run operates from a personal fork rather than the upstream repository.
If you already have permission to push to the source repo or upgradeAsFork=false,
the task is skipped. Authenticate gh first so forks can be created when needed.';
    }

    /**
     * 1. Skip immediately if forks are disabled.
     * 2. Detect the upstream GitHub slug and authenticated user via gh cli.
     * 3. Create the fork when missing and update ModuleUpgrader git links.
     * 4. Rewire the local clone (if it already exists) so origin=fork and upstream=source.
     */
    public function runActualTask($params = []): ?string
    {
        if (! $this->mu()->getUpgradeAsFork()) {
            $this->mu()->colourPrint('Skipping fork: upgradeAsFork=false', 'yellow');
            return null;
        }

        $originalGitLink = trim((string) $this->mu()->getGitLink());
        if ($originalGitLink === '') {
            return 'No git link configured for this module, cannot create fork.';
        }

        $gitHelper = Git::inst($this->mu());
        $sourceSlug = $gitHelper->resolveGitHubRepoSlug();
        if ($sourceSlug === '') {
            return 'ForkRepository currently only supports GitHub remotes. Unable to parse slug from ' . $originalGitLink;
        }

        $currentAdmin = $this->mu()->getCurrentUserIsAdmin();
        if ($currentAdmin === null) {
            $currentAdmin = $gitHelper->currentUserIsAdmin($sourceSlug);
            $this->mu()->setCurrentUserIsAdmin($currentAdmin);
        }
        if ($currentAdmin === true) {
            $this->mu()->colourPrint('Admin rights detected; forking only because upgradeAsFork=true.', 'light_cyan');
        }

        $delimiterPos = strpos($sourceSlug, '/');
        if ($delimiterPos === false) {
            return 'Unable to determine repository name from slug: ' . $sourceSlug;
        }
        $repoName = substr($sourceSlug, $delimiterPos + 1);
        if ($repoName === '') {
            return 'Unable to determine repository name from slug: ' . $sourceSlug;
        }

        $forkOwner = $this->determineGitHubUser();
        if ($forkOwner === '') {
            return 'Unable to determine authenticated GitHub user via gh cli. Please run "gh auth login" and try again.';
        }

        $forkSlug = $forkOwner . '/' . $repoName;

        if (! $this->forkExists($forkSlug)) {
            $this->createFork($sourceSlug, $forkSlug);
        } else {
            $this->mu()->colourPrint('Fork already exists: ' . $forkSlug, 'light_cyan');
        }

        $forkSsh = 'git@github.com:' . $forkSlug . '.git';
        $forkHttps = 'https://github.com/' . $forkSlug;
        $forkRaw = 'https://raw.githubusercontent.com/' . $forkSlug;

        $this->recordOriginalGitLink($originalGitLink);

        $this->mu()
            ->setGitLink($forkSsh)
            ->setGitLinkAsHTTPS($forkHttps)
            ->setGitLinkAsRawHTTPS($forkRaw);

        $this->rewireLocalRemotes($forkSsh, $originalGitLink);

        return null;
    }

    protected function hasCommitAndPush()
    {
        return false;
    }

    protected function determineGitHubUser(): string
    {
        return trim(
            $this->mu()->execMeGetReturnString(
                $this->mu()->getWebRootDirLocation(),
                "gh api user --jq '.login' 2>/dev/null",
                'determine GitHub user',
                false,
                '',
                false
            )
        );
    }

    protected function forkExists(string $forkSlug): bool
    {
        $result = trim(
            $this->mu()->execMeGetReturnString(
                $this->mu()->getWebRootDirLocation(),
                'gh repo view ' . escapeshellarg($forkSlug) . ' --json name --jq .name 2>/dev/null || true',
                'check if fork exists: ' . $forkSlug,
                false,
                '',
                false
            )
        );

        return $result !== '';
    }

    protected function createFork(string $sourceSlug, string $forkSlug): void
    {
        $this->mu()->colourPrint('Creating fork: ' . $forkSlug, 'green');
        $this->mu()->execMe(
            $this->mu()->getWebRootDirLocation(),
            'gh repo fork ' . escapeshellarg($sourceSlug) . ' --clone=false --remote=false',
            'create GitHub fork ' . $forkSlug,
            false
        );
    }

    protected function recordOriginalGitLink(string $originalGitLink): void
    {
        $this->mu()->getSessionManager()->setSessionValue('ForkRepositoryOriginalGitLink', $originalGitLink);
    }

    protected function rewireLocalRemotes(string $forkSsh, string $upstreamSsh): void
    {
        $gitRootDir = (string) $this->mu()->getGitRootDir();
        if ($gitRootDir === '' || ! is_dir($gitRootDir . '/.git')) {
            $this->mu()->colourPrint('Local clone not found yet; future clone will target fork.', 'light_cyan');
            return;
        }

        $this->mu()->execMe(
            $gitRootDir,
            'git remote set-url origin ' . escapeshellarg($forkSsh),
            'point origin at fork',
            false
        );

        $this->mu()->execMe(
            $gitRootDir,
            'if git remote get-url upstream >/dev/null 2>&1; then '
                . 'git remote set-url upstream ' . escapeshellarg($upstreamSsh) . '; '
                . 'else git remote add upstream ' . escapeshellarg($upstreamSsh) . '; fi',
            'ensure upstream remote points to original repository',
            false
        );
    }
}
