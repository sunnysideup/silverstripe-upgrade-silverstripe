<?php

namespace Sunnysideup\UpgradeSilverstripe\UpgradeRecipes;

class Ss4ToSs5FindAndReplaceOnlyModule extends BaseClass
{
    /**
     * name of the branch to be created that we use a starter branch for upgrade
     * @var string branch name
     */
    protected $nameOfUpgradeStarterBranch = 'upgrades/starting-point/ss5-upgrade';

    /**
     * name of the branch created to do the upgrade
     * @var string branch name
     */
    protected $nameOfTempBranch = 'upgrades/automatedbranch/4to5check';

    /**
     * The default namespace for all tasks
     * @var string
     */
    protected $defaultNamespaceForTasks = 'Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks';

    #########################################
    # TASKS
    #########################################

    /**
     * An array of all the 'taskNames of the tasks that you wish to run during the execution of this upgrader task.
     * This array can be overriden in the example-index.php file that you create.
     * You can enter a full name space if you need to.
     * The final -x will be removed.  We add -1 or -2 to run the same task multiple times.
     *
     * @var array
     */
    protected $listOfTasks = [
        //Step1: Prepare
        'WebRootDirCheckFoldersReady' => [],
        'WebRootDirReset-1' => [],
        'SwitchPhpVersion-1' => [
            'version' => '8.3',
        ],
        'CheckoutDefaultBranch-1' => [
            'branchOrTagToUse' => 'main',
        ],

        'BranchesAddTempUpgradeBranch' => [],
        // 'WebRootDirReset-3' => [],

        // //Step2: MoveToNewVersion
        'SearchAndReplace' => [
            'sourceFolders' => ['SS5'],
            'commitAndPush' => true,
            'runInRootDir' => false, // run in the whole project to also identify issues in modules...
        ],
    ];

    protected $frameworkComposerRestraint = '~5@stable';
}
