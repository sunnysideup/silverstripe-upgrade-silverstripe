<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\Helpers;

use Sunnysideup\UpgradeSilverstripe\Traits\HelperInst;

class Git
{
    use HelperInst;

    public function Clone(string $dir, string $gitLink, string $gitRootDir, ?string $branchName = 'master'): Git
    {
        $this->mu()->execMe(
            $dir,
            'git clone ' . $gitLink . ' ' . $gitRootDir,
            'clone ' . $gitLink . ' into ' . $gitRootDir,
            false
        );
        $this->mu()->execMe(
            $gitRootDir,
            'git branch -a',
            'check branch exists',
            false
        );
        $this->mu()->execMe(
            $gitRootDir,
            'git checkout ' . $branchName,
            'checkout ' . $branchName,
            false
        );
        $this->mu()->execMe(
            $gitRootDir,
            'git branch',
            'confirm branch',
            false
        );

        $this->fetchAll($gitRootDir);

        return $this;
    }

    public function renameBranch(string $dir, string $oldBranchName, string $newBranchName): Git
    {
        $this->fetchAll($dir);
        $oldBranchExists = $this->checkIfBranchExists($dir, $oldBranchName);
        $newBranchExists = $this->checkIfBranchExists($dir, $newBranchName);
        if (! $oldBranchExists) {
            echo "Branch " . $oldBranchName . " does not exist. Cannot rename.";
            return $this;
        }
        if ($newBranchExists) {
            echo "Branch " . $newBranchName . " already exists. Not renaming.";
            return $this;
        }

        // 1) ensure we’re on old branch and up to date
        $this->mu()->execMe($dir, 'git checkout ' . $oldBranchName, 'checkout old branch', false);
        $this->mu()->execMe($dir, 'git pull --ff-only', 'pull latest changes', false);

        // 2) rename locally
        $this->mu()->execMe($dir, 'git branch -m ' . $oldBranchName . ' ' . $newBranchName, 'rename branch', false);

        // 3) push main + set upstream (refspec is safest)
        $this->mu()->execMe(
            $dir,
            'git push -u origin ' . $newBranchName . ':' . $newBranchName,
            'push renamed branch to origin',
            false
        );

        // 4) make THIS clone treat origin/HEAD as main (no guessing)
        $this->mu()->execMe(
            $dir,
            'git remote set-head origin ' . $newBranchName,
            'set origin/HEAD to ' . $newBranchName,
            false
        );


        // 6) clean up stale refs
        $this->mu()->execMe($dir, 'git fetch --prune origin', 'prune old branches', false);

        // 7) if the local branch was tracking origin/master, repoint it
        $this->mu()->execMe(
            $dir,
            'git branch --unset-upstream || true',
            'unset upstream (if any)',
            false
        );
        $this->mu()->execMe(
            $dir,
            'git branch -u origin/' . $newBranchName . ' ' . $newBranchName,
            'set upstream to origin/' . $newBranchName,
            false
        );

        // 5) delete old remote branch (after GitHub default is switched!)
        $this->mu()->execMe(
            $dir,
            'git push origin --delete ' . $oldBranchName,
            'delete old branch on origin',
            false
        );


        return $this;
    }

    public function checkIfBranchExists(string $dir, string $branchName): bool
    {
        $this->fetchAll($dir);
        $output = $this->mu()->execMe(
            $dir,
            'git ls-remote --exit-code --heads origin ' . $branchName . ' >/dev/null 2>&1 && echo true || echo false',
            'check if branch ' . $branchName . ' exists in ' . $dir,
            false,
        );
        return array_pop($output) === 'true';
    }

    public function CommitAndPush(string $dir, string $message, string $branchName): Git
    {
        $this->fetchAll($dir);

        $this->mu()->execMe(
            $dir,
            'git add . -A',
            'git add all',
            false
        );

        $this->mu()->execMe(
            $dir,
            // 'if ! git diff --quiet; then git commit . -m "'.addslashes($message).'"; fi;',
            '
            if [ -z "$(git status --porcelain)" ]; then
                echo \'OKI DOKI - Nothing to commit\';
            else
                git commit . -m "' . addslashes($message) . '"
            fi',
            'commit changes: ' . $message,
            false
        );

        $this->mu()->execMe(
            $dir,
            'git push origin ' . $branchName,
            'pushing changes to origin on the ' . $branchName . ' branch',
            false
        );

        return $this;
    }

    public function deleteBranch(string $dir, string $branchName): Git
    {
        $this->fetchAll($dir);

        $this->deleteBranchLocally($dir, $branchName);
        $this->deleteBranchRemotely($dir, $branchName);

        return $this;
    }

    public function createNewBranchIfItDoesNotExist(string $dir, string $newBranchName, string $fromBranchName, ?bool $alsoCheckOutAndMakeDefault = false): Git
    {
        $this->fetchAll($dir);

        $this->checkoutBranch($dir, $fromBranchName);
        $this->mu()->execMe(
            $dir,
            '
            if $(git ls-remote --heads ${REPO} ${BRANCH} | grep -q ' . "'refs/heads/" . $newBranchName . "'" . '); then
                    echo "branch exists"
                else
                    git checkout -b ' . $newBranchName . ' ' . $fromBranchName . '
                    git push origin ' . $newBranchName . ';
            fi',
            'create branch ' . $newBranchName . ' from the ' . $fromBranchName . ' branch in ' . $dir,
            false
        );
        if ($alsoCheckOutAndMakeDefault) {
            $this->checkoutBranch($dir, $newBranchName);
        }
        $this->mu()->execMe(
            $dir,
            'git show-ref --verify --quiet refs/heads/' . $newBranchName . ' || git branch ' . $newBranchName . ' origin/' . $newBranchName . ' && git push -u origin ' . $newBranchName,
            'push it ',
            false
        );

        return $this;
    }

    public function createNewBranch(string $dir, string $newBranchName, string $fromBranch, ?bool $alsoCheckOutAndMakeDefault = false): Git
    {
        $this->fetchAll($dir);

        $this->checkoutBranch($dir, $fromBranch);
        $this->mu()->execMe(
            $dir,
            'git checkout -b ' . $newBranchName . ' ' . $fromBranch,
            'create and checkout new branch: ' . $newBranchName . ' from ' . $fromBranch,
            false
        );
        $makeDefault = '';
        if ($alsoCheckOutAndMakeDefault) {
            $makeDefault = ' -u ';
            $this->checkoutBranch($dir, $newBranchName);
        }
        $this->mu()->execMe(
            $dir,
            'git push ' . $makeDefault . ' origin ' . $newBranchName,
            'push it ',
            false
        );

        return $this;
    }

    public function checkoutBranch(string $dir, string $branch): Git
    {
        $this->fetchAll($dir);
        if ($this->checkIfBranchExists($dir, $branch)) {
            $this->mu()->execMe(
                $dir,
                'git checkout ' . $branch,
                'check out : ' . $branch,
                false
            );
        } else {
            $this->mu()->execMe(
                $dir,
                'git checkout -b ' . $branch,
                'check out : ' . $branch . ' as a starting point',
                false
            );
        }
        $this->mu()->execMe(
            $this->mu()->getGitRootDir(),
            'git pull origin ' . $branch,
            'get the latest details for : ' . $branch . '',
            false
        );

        return $this;
    }

    public function Merge(string $dir, string $fromBranch, string $intoBranch): Git
    {
        $this->fetchAll($dir);

        $this->mu()->execMe(
            $dir,
            '
                git checkout ' . $fromBranch . '
                git pull origin ' . $fromBranch . '
                git checkout ' . $intoBranch . '
                git merge --squash ' . $fromBranch . '
                git commit . -m "API:  upgrade merge"
                git push origin ' . $intoBranch . '
            ',
            'merging ' . $fromBranch . ' into ' . $intoBranch . ' in ' . $dir,
            false
        );

        return $this;
    }

    public function deleteBranchRemotely(string $dir, string $branchName): Git
    {
        $this->mu()->execMe(
            $dir,
            'git ls-remote --exit-code --heads origin ' . escapeshellarg($branchName) .
                ' > /dev/null 2>&1 && git push origin --delete ' . escapeshellarg($branchName) .
                ' || true',
            'delete branch ' . $branchName . ' remotely, if it exists.',
            false
        );

        return $this;
    }

    public function fetchAll(string $dir): Git
    {
        $this->mu()->execMe(
            $dir,
            'git fetch --all && git branch -a && git status',
            'get the latest',
            false,
            '',
            false //verbose = false!
        );

        return $this;
    }

    protected function deleteBranchLocally(string $dir, string $branchName): Git
    {
        $this->mu()->execMe(
            $dir,
            'if git show-ref --quiet refs/heads/' . $branchName . '; then git branch -d ' . $branchName . '; git push origin --delete ' . $this->mu()->getNameOfTempBranch() . '; fi',
            'delete branch (' . $branchName . ') locally',
            false
        );

        return $this;
    }

    public function resolveBranchOrTagToUse(?array $branchesToTryInThisOrder = []): string
    {
        if (empty($branchesToTryInThisOrder)) {
            $branchesToTryInThisOrder = ['develop', 'main', 'master'];
        }
        $alternativeCodeBase = (string) $this->mu()->getNameOfBranchForBaseCode();
        if ($alternativeCodeBase) {
            return $alternativeCodeBase;
        }

        $detected = $this->detectDefaultBranchFromRemoteHead();
        if ($detected) {
            return $detected;
        }

        $fallback = $this->detectFirstExistingBranchFromFallbackList($branchesToTryInThisOrder);
        if ($fallback) {
            return $fallback;
        }

        // empty means: clone with git default branch, and for composer we fall back to '@dev'
        return '';
    }

    protected function detectDefaultBranchFromRemoteHead(): string
    {
        $gitLink = (string) $this->mu()->getGitLink();
        if ($gitLink === '') {
            return '';
        }

        $output = (string) $this->mu()->execMe(
            $this->mu()->getWebRootDirLocation(),
            'git ls-remote --symref ' . escapeshellarg($gitLink) . ' HEAD',
            'detect default branch via remote HEAD',
            false
        );

        if (preg_match('/^ref:\s+refs\/heads\/([^\s]+)\s+HEAD\s*$/m', $output, $matches)) {
            return (string) ($matches[1] ?? '');
        }

        return '';
    }

    protected function detectFirstExistingBranchFromFallbackList(?array $branchesToTryInThisOrder = []): string
    {
        $gitLink = (string) $this->mu()->getGitLink();
        if ($gitLink === '') {
            return '';
        }

        $candidates = $branchesToTryInThisOrder;

        foreach ($candidates as $branch) {
            $output = (string) $this->mu()->execMe(
                $this->mu()->getWebRootDirLocation(),
                'git ls-remote --heads ' . escapeshellarg($gitLink) . ' ' . escapeshellarg($branch),
                'check if branch exists: ' . $branch,
                false
            );

            if (trim($output) !== '') {
                return $branch;
            }
        }

        return '';
    }
}
