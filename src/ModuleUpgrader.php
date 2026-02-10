<?php

namespace Sunnysideup\UpgradeSilverstripe;

use Sunnysideup\PHP2CommandLine\PHP2CommandLineSingleton;
use Sunnysideup\UpgradeSilverstripe\Traits\Creator;

class ModuleUpgrader extends ModuleUpgraderBaseWithVariables
{
    use Creator;

    ###############################
    # USEFUL COMMANDS
    ###############################

    /**
     * Executes given operations on the PHP2CommandLineSingleton instance
     * Documentation for this can be found in the PHP2CommandLineSingleton module
     *
     * @param  string  $newDir                  root dir for ommand
     * @param  string  $command                 actual command
     * @param  string  $comment                 comment
     * @param  boolean $alwaysRun               run even if you are just preparing a real run. Default FALSE
     *
     * @return array
     */
    public function execMe(
        string $newDir,
        string $command,
        string $comment,
        ?bool $alwaysRun = false,
        ?string $keyNotesLogFileLocation = '',
        ?bool $verbose = true
    ): array {
        if ($keyNotesLogFileLocation) {
            $this->commandLineExec
                ->setMakeKeyNotes(true)
                ->setKeyNotesFileLocation($keyNotesLogFileLocation);
        } else {
            $this->commandLineExec
                ->setMakeKeyNotes(false);
        }
        $this->commandLineExec->setErrorMessage('');
        if ($this->getBreakOnAllErrors()) {
            $this->commandLineExec->setErrorMessage('
------------------------------------------------------------------------
To continue, please use the following parameter: startFrom=' . $this->currentlyRunning . '
e.g. php runme.php startFrom=' . $this->currentlyRunning . '
------------------------------------------------------------------------
            ');
        }
        return $this->commandLineExec->execMe($newDir, $command, $comment, $alwaysRun, $verbose);
    }

    /**
     * Executes given operations on the PHP2CommandLineSingleton instance
     * Documentation for this can be found in the PHP2CommandLineSingleton module
     *
     * @param  string  $newDir                  root dir for ommand
     * @param  string  $command                 actual command
     * @param  string  $comment                 comment
     * @param  boolean $alwaysRun               run even if you are just preparing a real run. Default FALSE
     *
     * @return string
     */
    public function execMeGetReturnString(
        string $newDir,
        string $command,
        string $comment,
        ?bool $alwaysRun = false,
        ?string $keyNotesLogFileLocation = '',
        ?bool $verbose = true
    ): string {
        $output = $this->execMe($newDir, $command, $comment, $alwaysRun, $keyNotesLogFileLocation, $verbose);
        return implode("\n", array_filter(array_filter($output, 'trim')));
    }

    /**
     * Executes given operations on the PHP2CommandLineSingleton instance
     * Documentation for this can be found in the PHP2CommandLineSingleton module
     */
    public function colourPrint($mixedVar, string $colour = 'dark_gray', $newLineCount = 1)
    {
        return $this->commandLineExec->colourPrint($mixedVar, $colour, $newLineCount);
    }

    ###############################
    # RUN
    ###############################

    /**
     * Starts the command line output and prints some opening information to the output
     * also initalises various environment variables
     */
    public function run()
    {
        $this->applyRecipe();
        $this->colourPrint(
            '===================== START ======================',
            'white',
            5
        );
        $this->loadNextStepInstructions();
        $this->loadGlobalVariables();
        $this->loadCustomVariablesForTasks();
        foreach ($this->arrayOfModules as $moduleDetails) {
            $hasRun = false;
            $nextStep = '';
            $this->loadVarsForModule($moduleDetails);
            $this->workOutMethodsToRun();
            $this->printVarsForModule($moduleDetails);
            foreach ($this->listOfTasks as $fauxClassName => $params) {
                //get class without number
                $properClass = current(explode('-', $fauxClassName));
                if (! class_exists($properClass)) {
                    $testClass = $this->defaultNamespaceForTasks . '\\' . $properClass;
                    if (! class_exists($testClass)) {
                        foreach ($this->mu()->additionalNamespacesForTasks() as $add) {
                            $testClass = $this->defaultNamespaceForTasks . '\\' . $add . '\\' . $properClass;
                            if (class_exists($testClass)) {
                                break;
                            }
                        }
                    }
                    $properClass = $testClass;
                }
                if (class_exists($properClass)) {
                    $nameSpacesArray = explode('\\', $properClass);
                    $shortClassCode = end($nameSpacesArray);
                    $runItNow = $this->shouldWeRunIt((string) $fauxClassName);
                    $params['taskName'] = $shortClassCode;
                    $obj = $properClass::create($this, $params);
                    $taskName = $obj->getTaskName();
                    if ($taskName) {
                        $params['taskName'] = $taskName;
                    }
                    if ($hasRun && ! $nextStep) {
                        $nextStep = $params['taskName'];
                    }
                    if ($runItNow) {
                        $this->currentlyRunning = $fauxClassName;
                        $this->colourPrint('# --------------------', 'yellow', 3);
                        $this->colourPrint('# ' . $obj->getTitle() . ' (' . $params['taskName'] . ')', 'yellow');
                        $this->colourPrint('# --------------------', 'yellow');
                        $this->colourPrint('# ' . $obj->getDescriptionNice(), 'light_green');
                        $this->colourPrint('# --------------------', 'light_green');
                        // run it!!!
                        if ($this->getStatusOnly) {
                            $this->colourPrint('# status only - not running', 'yellow');
                            continue;
                        }
                        $obj->run();
                        if ($this->runInteractively) {
                            $hasRun = true;
                            if ($this->outOfOrderTask === false) {
                                $this->getSessionManager()->setSessionValue('Completed', $fauxClassName);
                            }
                        }
                    } else {
                        if (! $this->runInteractively) {
                            $this->colourPrint('# --------------------', 'yellow', 3);
                            $this->colourPrint('# ' . $obj->getTitle() . ' (' . $params['taskName'] . ')', 'yellow');
                            $this->colourPrint('# --------------------', 'yellow');
                            $this->colourPrint('# skipped', 'yellow');
                            $this->colourPrint('# --------------------', 'yellow');
                        }
                    }
                    $obj = $properClass::deleteTask($params);
                } else {
                    user_error(
                        $properClass . ' could not be found as class.
                        You can add namespacing to include your own classes.',
                        E_USER_ERROR
                    );
                }
            }
            $this->colourPrint(
                '===================== END =======================',
                'white',
                5
            );
            $this->colourPrint(
                'Next: ' . $nextStep,
                'yellow',
                5
            );
        }
        $this->endPHP2CommandLine();
    }

    public function applyRecipe(?string $recipeName = null)
    {
        if ($recipeName === null) {
            $recipeName = $this->getRecipe();
        }
        if ($recipeName) {
            if (isset($this->availableRecipes[$recipeName])) {
                $recipeClass = $this->availableRecipes[$recipeName];
                $obj = new $recipeClass();
                $vars = $obj->getVariables();
                foreach ($vars as $variable => $value) {
                    $methodSet = 'set' . ucwords($variable);
                    $this->{$methodSet}($value);
                }
            } else {
                user_error(
                    'Recipe ' . $recipeName . ' not available.
                    Available Recipes are: ' . print_r($this->getAvailableRecipes(), 1)
                );
            }
        }
    }

    public function getLastMethodRun(): string
    {
        return $this->getSessionManager()->getSessionValue('Completed');
    }

    protected function loadNextStepInstructions()
    {
        $this->getStatusOnly = $this->getCommandLineOrArgumentAsBoolean('status');
        if ($this->getStatusOnly) {
            $this->runInteractively = true;
        } else {
            $this->restartSession = $this->getCommandLineOrArgumentAsBoolean('restart');
            $this->runLastOneAgain = $this->getCommandLineOrArgumentAsBoolean('again');
            if ($this->getCommandLineOrArgumentAsString('startFrom')) {
                $this->startFrom = $this->getCommandLineOrArgumentAsString('startFrom');
                if ($this->runInteractively) {
                    $this->onlyRun = $this->getCommandLineOrArgumentAsString('startFrom');
                }
            }
            if ($this->getCommandLineOrArgumentAsString('endWith')) {
                $this->endWith = $this->getCommandLineOrArgumentAsString('endWith');
            }
            if ($this->getCommandLineOrArgumentAsString('task')) {
                $this->runInteractively = true;
                $this->onlyRun = $this->getCommandLineOrArgumentAsString('task');
                $this->outOfOrderTask = true;
            }
        }
    }

    protected function loadGlobalVariables()
    {
        $attempt = $this->aboveWebRootDirLocation;
        $this->aboveWebRootDirLocation = $this->checkIfPathExistsAndCleanItUp(
            $this->aboveWebRootDirLocation
        );
        if (! $this->aboveWebRootDirLocation) {
            die('You need the following directory for this application to work: ' . $attempt);
        }
        $this->webRootDirLocation = $this->checkIfPathExistsAndCleanItUp(
            $this->aboveWebRootDirLocation . '/' . $this->webRootName,
            true
        );
        $this->themeDirLocation = $this->checkIfPathExistsAndCleanItUp(
            $this->webRootDirLocation . '/themes',
            true
        );
    }

    protected function loadCustomVariablesForTasks()
    {
        foreach ($this->customVariablesForTasks as $taskName => $variableAndValue) {
            foreach ($variableAndValue as $variableName => $variableValue) {
                $key = $this->positionForTask($taskName);
                if ($key !== false) {
                    $this->listOfTasks[$taskName][$variableName] = $variableValue;
                } else {
                    user_error(
                        'Could not find ' . $taskName . '.
                        Choose from ' . implode(', ', array_keys($this->listOfTasks))
                    );
                    die('----');
                }
            }
        }
    }

    /**
     * Loads in and sets all the meta data for a module from the inputed array
     * @param array $moduleDetails
     */
    protected function loadVarsForModule($moduleDetails)
    {
        //Is Module Upgrade
        //do this first as a lot of other functions rely on it ...
        $this->isModuleUpgrade = isset($moduleDetails['IsModuleUpgrade']) ? (bool) $moduleDetails['IsModuleUpgrade'] : true;
        $this->isOnPackagist = isset($moduleDetails['IsOnPackagist']) ? (bool) $moduleDetails['IsOnPackagist'] : false;

        //VendorName
        $this->vendorName = $moduleDetails['VendorName'];

        //VendorNamespace
        if (isset($moduleDetails['VendorNamespace'])) {
            $this->vendorNamespace = $moduleDetails['VendorNamespace'];
        } else {
            $this->vendorNamespace = $this->cleanCamelCase($this->vendorName);
        }

        //PackageName
        $this->packageName = $moduleDetails['PackageName'];

        //PackageNamespace
        if (isset($moduleDetails['PackageNamespace'])) {
            $this->packageNamespace = $moduleDetails['PackageNamespace'];
        } else {
            $this->packageNamespace = $this->cleanCamelCase($this->packageName);
        }

        if (isset($moduleDetails['GitLink'])) {
            $this->gitLink = $moduleDetails['GitLink'];
        } else {
            $this->gitLink = 'git@github.com:' . $this->vendorName . '/silverstripe-' . $this->packageName . '.git';
            $this->gitLink = str_replace('silverstripe-silverstripe-', 'silverstripe-', $this->gitLink);
        }
        //see: https://stackoverflow.com/questions/5573334/remove-a-part-of-a-string-but-only-when-it-is-at-the-end-of-the-string
        $gitLinkWithoutExtension = preg_replace('/' . preg_quote('.git', '/') . '$/', '', $this->gitLink);
        $this->gitLinkAsHTTPS = str_replace(
            'git@github.com:',
            'https://github.com/',
            $gitLinkWithoutExtension
        );
        $this->gitLinkAsRawHTTPS = str_replace(
            'git@github.com:',
            'https://raw.githubusercontent.com/',
            $gitLinkWithoutExtension
        );
        $this->gitLinkAsHTTPS = str_replace(
            'git@bitbucket.org:',
            'https://bitbucket.org/',
            $gitLinkWithoutExtension
        );
        $this->gitLinkAsRawHTTPS = str_replace(
            'git@bitbucket.org:',
            'https://bitbucket.org/',
            $gitLinkWithoutExtension
        );
        if (stripos($this->gitLinkAsRawHTTPS, 'bitbucket') > 0) {
            $this->gitLinkAsRawHTTPS .= '/raw';
        }

        //Origin Composer FileLocation
        $this->originComposerFileLocation = $moduleDetails['OriginComposerFileLocation'] ?? '';

        $this->workoutPackageFolderName($moduleDetails);

        //ss4 location
        if (isset($moduleDetails['VendorAndPackageFolderNameForInstall'])) {
            $this->vendorAndPackageFolderNameForInstall = $moduleDetails['VendorAndPackageFolderNameForInstall'];
        } else {
            $this->vendorAndPackageFolderNameForInstall = strtolower($this->vendorName . '/' . $this->packageName);
        }

        //UpgradeAsFork
        if (array_key_exists('UpgradeAsFork', $moduleDetails)) {
            $this->setUpgradeAsFork(! empty($moduleDetails['UpgradeAsFork']));
        } else {
            // Ensure in-memory value matches any previously stored session value.
            $this->getUpgradeAsFork();
        }

        //NameOfBranchForBaseCode
        $this->nameOfBranchForBaseCode = $moduleDetails['NameOfBranchForBaseCode'] ?? $this->nameOfBranchForBaseCode;

        //LogFileLocation
        $this->logFileLocation = '';
        if ($this->logFolderDirLocation) {
            $this->logFileLocation =
                $this->logFolderDirLocation . '/' . $this->packageName .
                '-upgrade-log-' . date('Y-m-d') .
                '.txt';
            $this->commandLineExec->setLogFileLocation($this->logFileLocation);
        } else {
            $this->commandLineExec->setLogFileLocation('');
        }

        if ($this->restartSession) {
            $this->getSessionManager()->deleteSession();
        }
    }

    protected function vendorModuleLocation(): string
    {
        return $this->webRootDirLocation . '/vendor/' . $this->vendorName . '/' . $this->packageName;
    }

    protected function workoutPackageFolderName(array $moduleDetails): string
    {
        $this->packageFolderNameForInstall = trim($this->packageFolderNameForInstall);
        if ($this->packageFolderNameForInstall && $this->testExistenceFromRoot($this->packageFolderNameForInstall)) {
            //do nothing
        } else {
            $packageFolderNameForInstall = $this->getSessionManager()->getSessionValue(
                'PackageFolderNameForInstall'
            );
            if ($packageFolderNameForInstall) {
                $this->packageFolderNameForInstall = $packageFolderNameForInstall;
            } else {
                if (isset($moduleDetails['PackageFolderNameForInstall'])) {
                    $this->packageFolderNameForInstall = $moduleDetails['PackageFolderNameForInstall'];
                } else {
                    if (! $this->originComposerFileLocation) {
                        $this->originComposerFileLocation = $this->gitLinkAsRawHTTPS . '/' . $this->getNameOfBranchForBaseCode() . '/composer.json';
                    }
                    if ($this->URLExists($this->originComposerFileLocation)) {
                        $json = file_get_contents($this->originComposerFileLocation);
                        $array = json_decode($json, true);
                        if (isset($array['extra']['installer-name'])) {
                            $this->packageFolderNameForInstall = $array['extra']['installer-name'];
                        }
                    }
                }
            }
        }
        if (! $this->testLocationFromRootDir($this->packageFolderNameForInstall)) {
            $this->packageFolderNameForInstall = $this->getPackageFolderNameBasic(false);
            if (! $this->testLocationFromRootDir($this->packageFolderNameForInstall)) {
                $this->packageFolderNameForInstall = $this->getPackageFolderNameBasic(true);
            }
        }
        if ($this->testLocationFromRootDir($this->packageFolderNameForInstall)) {
            $this->getSessionManager()->setSessionValue(
                'PackageFolderNameForInstall',
                $this->packageFolderNameForInstall
            );
        } else {
            // user_error('
            //     Could not find: '.$this->webRootDirLocation . '/' .$this->packageFolderNameForInstall.'
            //     Composer File Used: '.$this->originComposerFileLocation .',
            //     Session Value: '.$packageFolderNameForInstall
            // );
        }
        if (! $this->packageFolderNameForInstall) {
            $this->packageFolderNameForInstall = $this->getPackageName();
        }
        return $this->packageFolderNameForInstall;
    }

    protected function testLocationFromRootDir(string $dir): bool
    {
        return (bool) file_exists($this->webRootDirLocation . '/' . $dir);
    }

    protected function printVarsForModule(array $moduleDetails)
    {
        $obj = new ModuleUpgraderInfo();

        return $obj->printVarsForModule($this, $moduleDetails);
    }

    /**
     * work out the current one to run!
     * @return string
     */
    protected function workOutMethodsToRun()
    {
        if ($this->runInteractively) {
            if ($this->startFrom || $this->endWith) {
                user_error('In interactive mode you can not set StartFrom / EndWith / OnlyRun.');
            }
            if ($this->onlyRun) {
            } else {
                $lastMethod = $this->getLastMethodRun();
                if ($lastMethod) {
                    $this->verbose = false;
                    $arrayKeys = array_keys($this->listOfTasks);
                    $found = false;
                    foreach ($arrayKeys as $index => $key) {
                        if ($key === $lastMethod) {
                            $found = true;
                            if ($this->runLastOneAgain) {
                                $this->onlyRun = $arrayKeys[$index] ?? 'could not find the task to run again';
                            } else {
                                if (isset($arrayKeys[$index + 1])) {
                                    if (isset($this->listOfTasks[$arrayKeys[$index + 1]])) {
                                        $this->onlyRun = $arrayKeys[$index + 1];
                                    } else {
                                        user_error('Can not find next task: ' . $arrayKeys[$index + 1]);
                                    }
                                } else {
                                    $this->getSessionManager()->deleteSession();
                                    die('
==========================================
Session has completed.
==========================================
                                    ');
                                }
                            }
                        }
                    }
                    if (! $found) {
                        user_error('Did not find next step.');
                    }
                } else {
                    $this->verbose = true;
                    reset($this->listOfTasks);
                    $this->onlyRun = key($this->listOfTasks);
                }
            }
        }

        return $this->onlyRun;
    }

    /**
     * start the method ...
     * - should we run it?
     *
     * @param  string $name whatever is listed in the listOfTasks
     */
    protected function shouldWeRunIt(string $name): bool
    {
        $runMe = true;
        if ($this->onlyRun) {
            return $name === $this->onlyRun ? true : false;
        }
        if ($this->lastMethodHasBeenRun) {
            $runMe = false;
        } else {
            if ($this->startFrom) {
                $runMe = false;
                if ($name === $this->startFrom) {
                    $this->startFrom = '';
                    $runMe = true;
                }
            }
            if ($this->endWith) {
                if ($name === $this->endWith) {
                    $this->lastMethodHasBeenRun = true;
                }
            }
        }

        //here we call the PHP2CommandLine

        return $runMe;
    }
}
