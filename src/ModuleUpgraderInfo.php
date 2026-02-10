<?php

namespace Sunnysideup\UpgradeSilverstripe;

class ModuleUpgraderInfo
{
    protected $mu = null;

    public function printVarsForModule($mu, $moduleDetails)
    {
        $this->mu = $mu;

        //output the confirmation.
        $mu->colourPrint('---------------------', 'light_cyan');
        $mu->colourPrint('UPGRADE DETAILS', 'light_cyan');
        $mu->colourPrint('---------------------', 'light_cyan');

        $mu->colourPrint('- Type: ' . $mu->getIsModuleUpgradeNice(), 'light_cyan');

        $mu->colourPrint('- Visibility: ' . $mu->getIsOnPackagistNice(), 'light_cyan');

        $mu->colourPrint('- Recipe: ' . ($mu->getRecipe() ?: 'no recipe selected'), 'light_cyan');

        $mu->colourPrint('- Available Recipes: ' . implode(', ', array_keys($mu->getAvailableRecipes())), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- Vendor Name: ' . $mu->getVendorName(), 'light_cyan');

        $mu->colourPrint('- Package Name: ' . $mu->getPackageName(), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- Upgrade as Fork: ' . ($mu->getUpgradeAsFork() ? 'yes' : 'no'), 'light_cyan');

        $adminStatus = $mu->getCurrentUserIsAdmin();
        $adminLabel = 'unknown';
        if ($adminStatus === true) {
            $adminLabel = 'yes';
        } elseif ($adminStatus === false) {
            $adminLabel = 'no';
        }
        $mu->colourPrint('- Current User Admin: ' . $adminLabel, 'light_cyan');

        $mu->colourPrint('- Run Interactively: ' . ($mu->getRunInteractively() ? 'yes' : 'no'), 'light_cyan');

        $mu->colourPrint('- Run Irreversibly: ' . ($mu->getRunIrreversibly() ? 'yes' : 'no'), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- Vendor Namespace: ' . $mu->getVendorNamespace(), 'light_cyan');

        $mu->colourPrint('- Package Namespace: ' . $mu->getPackageNamespace(), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint(
            '- Code Base Branch (what we started from): ' . $mu->getNameOfBranchForBaseCode(),
            'light_cyan'
        );

        $mu->colourPrint(
            '- Base Upgrade Branch (edit this branch manually if needed): ' . $mu->getNameOfUpgradeStarterBranch(),
            'light_cyan'
        );

        $mu->colourPrint(
            '- Automated Upgrade Branch (temp only, do not edit manually!): ' . $mu->getNameOfTempBranch(),
            'light_cyan'
        );

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- Upgrade Dir (root of install): ' . $mu->getWebRootDirLocation(), 'light_cyan');

        $mu->colourPrint('- Package Folder Name For Install: ' . $mu->getPackageFolderNameForInstall(), 'light_cyan');

        $mu->colourPrint('- Module / Project Dir(s): ' . implode(', ', $mu->getModuleDirLocations()), 'light_cyan');

        $mu->colourPrint('- Theme Dir: ' . ($mu->getThemeDirLocation() ?: 'not set'), 'light_cyan');

        $mu->colourPrint('- Git and Composer Root Dir: ' . $mu->getGitRootDir(), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- Git Repository Link (SSH): ' . $mu->getGitLink(), 'light_cyan');

        $mu->colourPrint('- Git Repository Link (HTTPS): ' . $mu->getGitLinkAsHTTPS(), 'light_cyan');

        $mu->colourPrint('- Git Repository Link (RAW): ' . $mu->getGitLinkAsRawHTTPS(), 'light_cyan');

        $mu->colourPrint('- Origin composer file location: ' .
            ($mu->getOriginComposerFileLocation() ?: 'not set'), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- Session file: ' . $mu->getSessionManager()->getSessionFileLocation(), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- Last Step: ' . ($mu->getLastMethodRun() ?: 'not set'), 'light_cyan');

        $mu->colourPrint('- Current Step: ' . ($mu->getOnlyRun() ?: 'not set'), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- Log File Location: ' . ($mu->getLogFileLocation() ?: 'not logged'), 'light_cyan');

        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('- List of Steps: ' . $mu->newLine() . $this->listOfTasks(), 'light_cyan');

        $mu->colourPrint('---------------------', 'light_cyan');

        $mu->colourPrint('paramaters available:', 'light_cyan');
        $mu->colourPrint('- status                    shows the current status without running any tasks', 'white');
        $mu->colourPrint('- task=MySpecificTask       to run any task out of order', 'white');
        $mu->colourPrint('- startFrom=MySpecificTask  to start from a specific step', 'white');
        $mu->colourPrint('- again                     run the last step again', 'white');
        $mu->colourPrint('- restart                   starts process from beginning', 'white');
        $mu->colourPrint('- ---', 'light_cyan');

        $mu->colourPrint('if running all tasks at once, you can also use:', 'light_cyan');
        $mu->colourPrint('- ---', 'light_cyan');
        $mu->colourPrint('- endWith=MySpecificTask    runs all steps up to MySpecificTask', 'white');

        $mu->colourPrint('- ---', 'light_cyan');
        $mu->colourPrint('if running step by step, you can also edit the session file (see file location above) ', 'light_cyan');
        $mu->colourPrint('to start at a specific step (e.g. bypass current one). ', 'light_cyan');
        $mu->colourPrint('- ---', 'light_cyan');
    }

    protected function listOfTasks($currentOne = ''): string
    {
        $tasks = $this->mu->getListOfTasks();
        if (count($tasks) === 0) {
            user_error('Please make sure to select a task or set a valid recipe (e.g. SS4)');
        }
        if (! $currentOne) {
            $currentOne = $this->mu->getOnlyRun();
        }
        $customVariables = $this->mu->getCustomVariablesForTasks();
        $count = 0;
        $string = '';
        foreach ($tasks as $task => $variables) {
            $customVars = $customVariables[$task] ?? [];
            $variables += $customVars;
            $count++;
            $addAfter = ($currentOne === $task ? ' (CURRENT ONE)' : '');
            $addBefore = ($currentOne === $task ? ' **** ' : '');
            $string .= $this->mu->newLine() . '- ' . $count . $addBefore . ': ' . $task . $addAfter;
            if (count($variables)) {
                foreach ($variables as $variableName => $variableValue) {
                    $string .= $this->mu->newLine() . '  ... ' . $variableName . ' = ' . print_r($variableValue, 1) . $this->mu->newLine();
                }
            }
        }

        return $string;
    }
}
