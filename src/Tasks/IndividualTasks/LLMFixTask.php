<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\EasyCodingStandards;
use Sunnysideup\PHP2CommandLine\PHP2CommandLineSingleton;
use Sunnysideup\UpgradeSilverstripe\Api\FileSystemFixes;
use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Composer;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;

/**
 * Adds a new branch to your repository that is going to be used for upgrading it.
 */
abstract class LLMFixTask extends Task
{
    protected $taskStep = 'ANY';

    protected string $llmInstruction;

    /**
     *
     * works like this:
     * ```php
     * [
     *     './**\/*.php' => 'getCMSFields',
     *     './**\/*.php' => 'updateCMSFields',
     * ]
     * ```
     * @var array
     */
    protected array $llmFileSelection;
    protected string $alternativePathToLLMInstruction = '';

    abstract public function getTitle(): string;
    abstract public function getDescription(): string;

    public function runActualTask($params = []): ?string
    {
        if (!$this->llmInstruction) {
            throw new \Exception('No LLM Instruction has been set - set something like SS5/CleanUpSomething.txt');
        }
        if (empty($this->llmFileSelection)) {
            throw new \Exception('No LLM File Selection has been set - set something like ./**/SomeFolder/*.php => getCMSFields');
        }
        $path = $this->alternativePathToLLMInstruction ?: $this->defaultPathForLLMInstruction();
        $prompt = $path . DIRECTORY_SEPARATOR . $this->llmInstruction;
        if (!file_exists($prompt)) {
            throw new \Exception('LLM Instruction file not found: ' . $prompt);
        }
        EasyCodingStandards::installIfNotInstalled($this->mu());
        foreach ($this->llmFileSelection as $filePattern => $method) {
            foreach ($this->mu()->getExistingModuleDirLocations() as $moduleDir) {
                $command = EasyCodingStandards::prependCommand() . 'sake-llm-opencode ' .
                    $moduleDir .
                    ' --files=' . escapeshellarg($filePattern) .
                    ' --prompt=' . $prompt;
                if ($method) {
                    $command .=
                        ' --contains=' . escapeshellarg($method);
                }
                $this->mu()->execMe(
                    $this->mu()->getWebRootDirLocation(),
                    $command,
                    'Fixing files ' . $filePattern . ' in ' . $moduleDir . ' containing "' . $method . '"',
                    true
                );
            }
        }

        EasyCodingStandards::removeIfInstalled($this->mu());
        return null;
    }

    protected function hasCommitAndPush(): bool
    {
        return true;
    }

    protected function defaultPathForLLMInstruction(): string
    {
        return $this->mu()->getLocationOfThisUpgrader() . DIRECTORY_SEPARATOR . 'AgentPrompts';
    }
}
