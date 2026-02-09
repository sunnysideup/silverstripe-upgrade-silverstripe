<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks\FiveToSix;

use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\EasyCodingStandards;
use Sunnysideup\PHP2CommandLine\PHP2CommandLineSingleton;
use Sunnysideup\UpgradeSilverstripe\Api\FileSystemFixes;
use Sunnysideup\UpgradeSilverstripe\Tasks\Helpers\Composer;
use Sunnysideup\UpgradeSilverstripe\Tasks\Task;
use Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks\LLMFixTask;

/**
 * Adds a new branch to your repository that is going to be used for upgrading it.
 */
class FixBuildTasks extends LLMFixTask
{
    protected $taskStep = 'SS5->SS6';

    protected string $llmInstruction = 'SS6/FixBuildTasks.txt';
    protected array $llmFileSelection = [
        './**/*.php' => 'extends BuildTask'
    ];

    protected string $alternativePathToLLMInstruction = '';

    public function getTitle(): string
    {
        return 'Fix build tasks for Silverstripe 6.';
    }

    public function getDescription(): string
    {
        return '
            Uses opencode to fix the build tasks for Silverstripe 6. ';
    }
}
