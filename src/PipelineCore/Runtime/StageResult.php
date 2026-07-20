<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

final class StageResult
{
    /**
     * @param array<string, StepResult> $stepResults
     */
    public function __construct(
        public readonly Stage $stage,
        public readonly bool $success,
        public readonly array $stepResults,
        public readonly ?StepResult $failedStep = null,
    ) {}

    /**
     * @param array<string, StepResult> $stepResults
     */
    public static function succeeded(Stage $stage, array $stepResults = []): self
    {
        return new self(
            stage: $stage,
            success: true,
            stepResults: $stepResults,
            failedStep: null,
        );
    }

    public static function empty(Stage $stage): self
    {
        return self::succeeded($stage, []);
    }

    /**
     * @param array<string, StepResult> $stepResults
     */
    public static function failed(Stage $stage, array $stepResults, StepResult $failedStep): self
    {
        return new self(
            stage: $stage,
            success: false,
            stepResults: $stepResults,
            failedStep: $failedStep,
        );
    }

    public function errorMessage(): ?string
    {
        return $this->failedStep?->errorMessage;
    }
}
