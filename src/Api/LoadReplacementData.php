<?php

namespace Sunnysideup\UpgradeSilverstripe\Api;

/**
 * loads yml data if strings to replace in
 * code.
 *
 * The replacements should be in the same folder as this class.
 *
 * Alternatively, you can specify another folderContainingReplacementData in the
 * construct method.
 *
 * It will also search the root folders for any packages / projects being upgraded.
 * replacement data can be found in any path provided like this:
 *
 *     PATH / toFolder / '.upgrade.replacements.yml'
 *
 * toFolders have names like: SS37 or SS4.
 */

use Sunnysideup\UpgradeSilverstripe\ModuleUpgrader;
use Symfony\Component\Yaml\Yaml;

class LoadReplacementData
{
    /**
     * Standard file name
     */
    protected $ymlFileName = '.upgrade.replacements.yml';

    /**
     * Module Object
     * @var ModuleUpgrader
     */
    protected $myMu = null;

    /**
     * folder containing the replacement file
     *
     * @var string
     */
    protected $folderContainingReplacementData = '';

    /**
     * name of the sub-folder for the replacement data.
     * @var string
     */
    protected $toFolders = 'SS4';

    /**
     * array of replacements
     * @var array
     */
    protected $fullArray = [];

    /**
     * @var array
     */
    protected $languages = [];

    /**
     * @var array
     */
    protected $flatFindArray = [];

    /**
     * @var array
     */
    protected $flatReplacedArray = [];

    /**
     * path where to look for data.
     * @var array
     */
    protected $paths = [];

    protected $toFolder = '';

    /**
     * @param ModuleUpgrader $mu
     * @param string         $alternativeReplacementDataFolder
     * @param string         $toFolder - the subfolder used for the specific replace`
     */
    public function __construct($mu, $alternativeReplacementDataFolder = '', $toFolder = 'SS4')
    {
        $this->myMu = $mu;
        $this->folderContainingReplacementData = $alternativeReplacementDataFolder ?: $this->defaultLocation();
        $this->toFolder = $toFolder;

        $this->compileFlatArray();
    }

    public function setToFolder(string $s)
    {
        $this->toFolder = $s;

        return $this;
    }

    public function getReplacementArrays(): array
    {
        return $this->fullArray;
    }

    public function getLanguages()
    {
        return $this->languages;
    }

    public function getFlatFindArray(): array
    {
        return $this->flatFindArray;
    }

    public function getFlatReplacedArray(): array
    {
        return $this->flatReplacedArray;
    }

    protected function mu()
    {
        return $this->myMu;
    }

    protected function defaultLocation()
    {
        return $this->mu()->getLocationOfThisUpgrader() . DIRECTORY_SEPARATOR . 'ReplacementData';
    }

    protected function compileFlatArray()
    {
        $this->fullArray = $this->getData();
        $count = 0;
        foreach ($this->fullArray as $path => $pathArray) {
            foreach ($pathArray as $language => $languageArray) {
                $this->languages[$language] = $language;
                foreach ($languageArray as $findKey => $findKeyArray) {
                    if (! isset($findKeyArray['R'])) {
                        user_error('replacement key not set: ' . print_r($findKeyArray, true));
                    }
                    $replaceKey = $findKeyArray['R'];
                    $key = strtolower($language . '_' . $path . '_' . $count);
                    $this->flatFindArray[$key] = $findKey;
                    $this->flatReplacedArray[$key] = $replaceKey;
                    $count++;
                }
            }
        }
    }

    /**
     * retrieve all replacements
     */
    protected function getData(): array
    {
        $this->getPaths();
        // Merge with any other upgrade spec in the top level
        $config = [];
        foreach ($this->paths as $path) {
            $file = $path . DIRECTORY_SEPARATOR . $this->toFolder . DIRECTORY_SEPARATOR . $this->ymlFileName;
            if (file_exists($file)) {
                if (file_exists($file)) {
                    $nextConfig = Yaml::parse(file_get_contents(realpath($file)));
                }
                else {
                    $nextConfig = [];
                }
                // Merge
                $config = $this->mergeConfig($config, $nextConfig);
                $this->mu()->colourPrint('loaded replacement file: ' . $file);
            } else {
                $this->mu()->colourPrint('could not find: ' . $file);
            }
        }
        ksort($config);

        return $config;
    }

    /**
     * returns a list of paths to be checked for replacement data.
     */
    protected function getPaths(): array
    {
        $array = [];
        foreach ($this->mu()->getExistingModuleDirLocations() as $moduleDir) {
            $array[$moduleDir] = $moduleDir;
        }
        $globalFixes = $this->mu()->checkIfPathExistsAndCleanItUp($this->folderContainingReplacementData);
        if ($globalFixes) {
            $array[$globalFixes] = $globalFixes;
        }
        $this->paths = $array;

        return $this->paths;
    }

    /**
     * merge config of two files ...
     */
    protected static function mergeConfig(array $left, array $right): array
    {
        //see ConfigFile for original
        $merged = $left;
        foreach ($right as $key => $value) {
            // if non-associative, just merge in unique items
            if (is_numeric($key)) {
                if (! in_array($value, $merged, true)) {
                    $merged[] = $value;
                }
                continue;
            }

            // If not merged into left hand side, then simply assign
            if (! isset($merged[$key])) {
                $merged[$key] = $value;
                continue;
            }

            // Make sure both sides are the same type
            if (is_array($merged[$key]) !== is_array($value)) {
                user_error(
                    "Config option ${key} cannot merge non-array with array value."
                );
            }

            // If array type, then merge
            if (is_array($value)) {
                $merged[$key] = self::mergeConfig($merged[$key], $value);
                continue;
            }

            // If non array types, don't merge, but instead assert both values are set
            if ($merged[$key] !== $value) {
                user_error(
                    "Config option ${key} is defined with different values in multiple files."
                );
            }
        }

        return $merged;
    }
}
