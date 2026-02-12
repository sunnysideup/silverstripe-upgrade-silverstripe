<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\TestRecipes;

use Sunnysideup\UpgradeSilverstripe\UpgradeRecipes\BaseClass;

/**
 * Minimal recipe that only exercises GitHub permission/fork/PR tasks.
 */
class GitHubWorkflow extends BaseClass
{
    /**
     * @var string
     */
    protected $nameOfUpgradeStarterBranch = 'tests/github-workflow/starter';

    /**
     * @var string
     */
    protected $nameOfTempBranch = 'tests/github-workflow/temp';

    /**
     * @var string
     */
    protected $defaultNamespaceForTasks = 'Sunnysideup\\UpgradeSilverstripe\\Tasks\\IndividualTasks';

    /**
     * Only run permission/fork/PR tasks.
     *
     * @var array
     */
    protected $listOfTasks = [
        'WebRootDirCheckFoldersReady' => [],
        'CheckoutDefaultBranch-1' => [
            'clearCache' => false,
        ],
        'BranchAddUpgradeStarterBranch' => [],
        'CheckoutTempUpgradeBranch' => [],
        'CheckRepositoryPermissions' => [],
        'ForkRepository' => [],
        'PullRequest' => [],
    ];

    /**
     * Composer requirement placeholder for the test recipe.
     *
     * @var string
     */
    protected $frameworkComposerRestraint = '^6.0';
}
