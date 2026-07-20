<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

use Polymorph\Platform\PipelineCore\Runtime\PipelineDomainFailure;
use Polymorph\Platform\PipelineCore\Runtime\PipelineDomainFailureException;
use Polymorph\Platform\PipelineCore\Runtime\Stage;
use Polymorph\Platform\PipelineCore\Runtime\StageResult;

final class PipelineDomainFailureHttpMapper
{
    private ?ErrorFactory $cachedErrorFactory = null;

    public function map(PipelineDomainFailureException $exception): HttpErrorException
    {
        $result = $exception->result;
        $detail = $this->resolveDetail($result, $exception->fallbackMessage);
        $failedStep = $result->failedStageResult->failedStep;

        if ($failedStep?->errorCode instanceof ErrorCode) {
            $builder = $this->errorFactory()->for($failedStep->errorCode)
                ->detail($detail)
                ->meta($failedStep->metadata);

            if (is_string($failedStep->errorTitle) && $failedStep->errorTitle !== '') {
                $builder = $builder->title($failedStep->errorTitle);
            }

            return new HttpErrorException($builder->build());
        }

        if ($result->failedStage === Stage::VALIDATION) {
            return new HttpErrorException(
                $this->errorFactory()->for(ErrorCode::VALIDATION_ERROR)
                    ->detail($detail)
                    ->meta([
                        'reason' => 'pipeline_validation_failed',
                        ...$this->extractValidationMeta($result),
                    ])
                    ->build(),
            );
        }

        return new HttpErrorException(
            $this->errorFactory()->for($result->failedStage->defaultErrorCode())
                ->detail($detail)
                ->meta([
                    'reason' => 'pipeline_execution_failed',
                    'failed_stage' => $result->failedStage->value,
                ])
                ->build(),
        );
    }

    private function resolveDetail(PipelineDomainFailure $result, string $fallback): string
    {
        if ($result->failureMessage !== null && $result->failureMessage !== '') {
            return $result->failureMessage;
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractValidationMeta(PipelineDomainFailure $result): array
    {
        if ($result->failedStage !== Stage::VALIDATION) {
            return [];
        }

        $stageResult = $result->stageResults[Stage::VALIDATION->value] ?? null;
        if (! $stageResult instanceof StageResult) {
            return [];
        }

        foreach ($stageResult->stepResults as $stepResult) {
            $stepMetadata = $stepResult->metadata;
            if (! is_array($stepMetadata)) {
                continue;
            }

            $errors = $stepMetadata['errors'] ?? null;
            if (is_array($errors)) {
                return ['errors' => $errors];
            }
        }

        return [];
    }

    private function errorFactory(): ErrorFactory
    {
        // Единый общий ErrorFactory из контейнера, без пересборки из config.
        return $this->cachedErrorFactory ??= app(ErrorFactory::class);
    }
}
