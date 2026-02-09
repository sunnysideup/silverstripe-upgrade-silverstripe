<?php

declare(strict_types=1);

namespace Sunnysideup\UpgradeSilverstripe\Tasks\IndividualTasks\FiveToSix;


/**
 * Adds a new branch to your repository that is going to be used for upgrading it.
 */
class FixPagesGetCMSFields extends LLMFixTask
{
    protected $taskStep = 'SS5->SS6';

    protected string $llmInstruction = 'SS6/FixPagesGetCMSFields.txt';
    protected array $llmFileSelection = [
        './**/*Page.php' => 'getCMSFields',
        './**/Pages/**/*.php' => 'getCMSFields',
        './**/*.php' => 'extends Page'
    ];

    protected string $alternativePathToLLMInstruction = '';

    public function getTitle(): string
    {
        return 'Fix pages getCMSFields for Silverstripe 6.';
    }

    public function getDescription(): string
    {
        return '
            Uses opencode to fix the pages getCMSFields for Silverstripe 6. ';
    }
}
