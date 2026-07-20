<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

class PipelineDefinition
{
    /**
    * @param string $name Уникальное имя пайплайна (record.update, schema.save_with_fields)
     * @param array<string, Step[]> $steps Карта: Stage::value => Step[]
     * @param bool $requiresLock Нужна ли блокировка для всего пайплайна
     */
    public function __construct(
        public readonly string $name,
        public readonly array $steps,
        public readonly bool $requiresLock = true,
    ) {
        $this->validate();
    }

    /**
     * @return Stage[]
     */
    public function orderedStages(): array
    {
        return Stage::orderedList();
    }

    /**
     * Получить шаги для конкретной стадии
     * @return Step[]
     */
    public function getStepsForStage(Stage $stage): array
    {
        return $this->steps[$stage->value] ?? [];
    }

    /**
     * Валидация definition при создании
     */
    private function validate(): void
    {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Pipeline name cannot be empty');
        }

        $contextClasses = [];

        // Проверка: все steps реализуют Step interface
        foreach ($this->steps as $stageValue => $stepsArray) {
            if (!is_string($stageValue) || trim($stageValue) === '') {
                throw new \InvalidArgumentException('Stage key must be a non-empty string');
            }

            if (Stage::tryFrom($stageValue) === null) {
                throw new \InvalidArgumentException("Unknown stage key '{$stageValue}' in pipeline '{$this->name}'");
            }

            if (!is_array($stepsArray)) {
                throw new \InvalidArgumentException("Invalid steps collection in stage {$stageValue}: expected array");
            }

            foreach ($stepsArray as $step) {
                if (!$step instanceof Step) {
                    throw new \InvalidArgumentException(
                        "Invalid step in stage {$stageValue}: " . get_class($step)
                    );
                }

                $contextClass = $step->contextClass();
                if (!is_string($contextClass) || $contextClass === '') {
                    throw new \InvalidArgumentException(
                        "Invalid context class for step '{$step->name()}' in stage {$stageValue}"
                    );
                }

                if (!is_a($contextClass, PipelineContext::class, true)) {
                    throw new \InvalidArgumentException(
                        "Step '{$step->name()}' declares invalid context class '{$contextClass}' in stage {$stageValue}"
                    );
                }

                $contextClasses[$contextClass] = true;
            }
        }

        if (count($contextClasses) > 1) {
            throw new \InvalidArgumentException(
                "Pipeline '{$this->name}' contains incompatible step context classes: " . implode(', ', array_keys($contextClasses))
            );
        }

        $this->validateStepDependencies();
    }

    private function validateStepDependencies(): void
    {
        $availableSlots = [];

        foreach ($this->orderedStages() as $stage) {
            $stageSteps = $this->steps[$stage->value] ?? [];

            foreach ($stageSteps as $step) {
                foreach ($step->requires() as $requiredSlot) {
                    if (!is_string($requiredSlot) || trim($requiredSlot) === '') {
                        throw new \InvalidArgumentException(
                            "Step '{$step->name()}' in pipeline '{$this->name}' has invalid required slot"
                        );
                    }

                    if ($this->isInputSlot($requiredSlot)) {
                        continue;
                    }

                    if (!array_key_exists($requiredSlot, $availableSlots)) {
                        throw new \InvalidArgumentException(
                            "Step '{$step->name()}' in pipeline '{$this->name}' requires slot '{$requiredSlot}' before it is produced"
                        );
                    }
                }

                foreach ($step->produces() as $producedSlot) {
                    if (!is_string($producedSlot) || trim($producedSlot) === '') {
                        throw new \InvalidArgumentException(
                            "Step '{$step->name()}' in pipeline '{$this->name}' has invalid produced slot"
                        );
                    }

                    $availableSlots[$producedSlot] = true;
                }
            }
        }
    }

    private function isInputSlot(string $slot): bool
    {
        return str_starts_with($slot, 'input.');
    }
}
