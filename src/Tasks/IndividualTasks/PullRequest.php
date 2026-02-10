<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Git;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

class PullRequest extends Task
{
    protected $taskStep = 'ANY';

    /**
     * Optional overrides supplied via task params.
     */
    protected string $sourceBranchOverride = '';
    protected string $targetBranchOverride = '';
    protected string $pullRequestTitle = '';
    protected string $pullRequestBody = '';

    public function getTitle()
    {
        return 'Create Pull Request';
    }

    public function getDescription()
    {
        return 'If this upgrade ran on a fork, push the working branch to the fork and
open a GitHub pull request back to the upstream repository using gh CLI.';
    }

    public function runActualTask($params = []): ?string
    {
        if (! $this->mu()->getUpgradeAsFork()) {
            $this->mu()->colourPrint('Skipping PR creation: upgradeAsFork=false', 'yellow');
            return null;
        }

        if (! $this->ghCliAvailable()) {
            return 'GitHub CLI (gh) is required to create pull requests but was not found in PATH.';
        }

        $session = $this->mu()->getSessionManager();
        $originalGitLink = trim($session->getSessionValue('ForkRepositoryOriginalGitLink'));
        $forkGitLink = trim($session->getSessionValue('ForkRepositoryForkGitLink'));
        $originalSlug = trim($session->getSessionValue('ForkRepositoryOriginalSlug'));
        $forkSlug = trim($session->getSessionValue('ForkRepositoryForkSlug'));

        if ($originalGitLink === '' || $forkGitLink === '' || $originalSlug === '' || $forkSlug === '') {
            return 'Missing fork metadata in session. Please re-run ForkRepository before creating a pull request.';
        }

        $gitRootDir = (string) $this->mu()->getGitRootDir();
        if ($gitRootDir === '' || ! is_dir($gitRootDir . '/.git')) {
            return 'Cannot locate local git repository at ' . $gitRootDir;
        }

        $sourceBranch = $this->resolveSourceBranch();
        $targetBranch = $this->resolveTargetBranch();

        $this->ensureRemote($gitRootDir, 'origin', $forkGitLink);
        $this->ensureRemote($gitRootDir, 'upstream', $originalGitLink);

        Git::inst($this->mu())->fetchAll($gitRootDir);

        $branchExists = $this->branchExists($gitRootDir, $sourceBranch);
        if (! $branchExists) {
            return 'Source branch ' . $sourceBranch . ' not found in local repository. Cannot open PR.';
        }

        $this->pushSourceBranch($gitRootDir, $sourceBranch);

        $existing = $this->findExistingPr($gitRootDir, $originalSlug, $targetBranch, $forkSlug, $sourceBranch);
        if ($existing !== '') {
            $session->setSessionValue('PullRequestUrl', $existing);
            $this->mu()->colourPrint('Pull request already exists: ' . $existing, 'light_cyan');
            return null;
        }

        $title = $this->pullRequestTitle ?: $this->defaultTitle($sourceBranch);
        $body = $this->pullRequestBody ?: $this->defaultBody();

        $prUrl = $this->createPullRequest($gitRootDir, $originalSlug, $targetBranch, $forkSlug, $sourceBranch, $title, $body);
        if ($prUrl === '') {
            return 'Failed to create pull request via gh CLI. Please check logs above.';
        }

        $session->setSessionValue('PullRequestUrl', $prUrl);
        $this->mu()->colourPrint('Pull request created: ' . $prUrl, 'green');

        return null;
    }

    protected function hasCommitAndPush()
    {
        return false;
    }

    protected function ghCliAvailable(): bool
    {
        $result = trim(
            $this->mu()->execMeGetReturnString(
                $this->mu()->getWebRootDirLocation(),
                'command -v gh 2>/dev/null || which gh 2>/dev/null',
                'detect gh cli',
                false,
                '',
                false
            )
        );

        return $result !== '';
    }

    protected function resolveSourceBranch(): string
    {
        if ($this->sourceBranchOverride !== '') {
            return $this->sourceBranchOverride;
        }

        $tempBranch = trim((string) $this->mu()->getNameOfTempBranch());
        if ($tempBranch !== '') {
            return $tempBranch;
        }

        return 'main';
    }

    protected function resolveTargetBranch(): string
    {
        if ($this->targetBranchOverride !== '') {
            return $this->targetBranchOverride;
        }

        $baseBranch = trim((string) $this->mu()->getNameOfBranchForBaseCode());
        if ($baseBranch !== '') {
            return $baseBranch;
        }

        return 'main';
    }

    protected function ensureRemote(string $dir, string $remote, string $url): void
    {
        $command = 'if git remote get-url ' . $remote . ' >/dev/null 2>&1; then '
            . 'git remote set-url ' . $remote . ' ' . escapeshellarg($url) . '; '
            . 'else git remote add ' . $remote . ' ' . escapeshellarg($url) . '; fi';

        $this->mu()->execMe($dir, $command, 'ensure git remote ' . $remote . ' points to ' . $url, false);
    }

    protected function branchExists(string $dir, string $branch): bool
    {
        $command = 'BR=' . escapeshellarg($branch)
            . '; if git show-ref --verify --quiet refs/heads/"$BR"; then echo yes; else echo no; fi';

        $result = trim(
            $this->mu()->execMeGetReturnString(
                $dir,
                $command,
                'confirm branch exists: ' . $branch,
                false,
                '',
                false
            )
        );

        return $result === 'yes';
    }

    protected function pushSourceBranch(string $dir, string $branch): void
    {
        $this->mu()->execMe(
            $dir,
            'git push -u origin ' . escapeshellarg($branch),
            'push branch ' . $branch . ' to fork',
            false
        );
    }

    protected function findExistingPr(
        string $dir,
        string $repoSlug,
        string $baseBranch,
        string $forkSlug,
        string $sourceBranch
    ): string {
        return trim(
            $this->mu()->execMeGetReturnString(
                $dir,
                'gh pr list --state open --limit 1 '
                . '--base ' . escapeshellarg($baseBranch) . ' '
                . '--head ' . escapeshellarg($forkSlug . ':' . $sourceBranch) . ' '
                . '--json url --jq ".[].url" '
                . '--repo ' . escapeshellarg($repoSlug),
                'check for existing pull request',
                false,
                '',
                false
            )
        );
    }

    protected function createPullRequest(
        string $dir,
        string $repoSlug,
        string $baseBranch,
        string $forkSlug,
        string $sourceBranch,
        string $title,
        string $body
    ): string {
        return trim(
            $this->mu()->execMeGetReturnString(
                $dir,
                'gh pr create '
                . '--repo ' . escapeshellarg($repoSlug) . ' '
                . '--base ' . escapeshellarg($baseBranch) . ' '
                . '--head ' . escapeshellarg($forkSlug . ':' . $sourceBranch) . ' '
                . '--title ' . escapeshellarg($title) . ' '
                . '--body ' . escapeshellarg($body),
                'create pull request',
                false,
                '',
                false
            )
        );
    }

    protected function defaultTitle(string $sourceBranch): string
    {
        return 'Automated upgrade: ' . $sourceBranch;
    }

    protected function defaultBody(): string
    {
        return "This pull request was generated automatically by the Silverstripe Upgrader.\n"
            . 'Please review the changes and merge when ready.';
    }
}
