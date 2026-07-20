<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation;

use Illuminate\Contracts\Validation\ValidationRule;
use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\Cardinality;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\FieldType;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\ValidationRules;
use Polymorph\Platform\Domain\SchemaModelValidation\Cache\RuleSetCache;
use Polymorph\Platform\Domain\SchemaModelValidation\Compilers\RuleCompilerRegistry;
use Polymorph\Platform\Domain\SchemaModelValidation\Contracts\SchemaValidationRulesEngineInterface;
use Polymorph\Platform\Domain\SchemaModelValidation\Rules\Iso8601DateTime;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\FieldDescriptor;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class RecordValidationService implements SchemaValidationRulesEngineInterface
{
    public function __construct(
        private readonly RuleCompilerRegistry $ruleCompilerRegistry,
        private readonly RuleSetCache $ruleSetCache,
    ) {}

    public function buildLaravelRulesForDescriptor(SchemaDescriptor $schemaDescriptor): array
    {
        $schemaId = $schemaDescriptor->schemaId;

        $cached = $this->ruleSetCache->get($schemaId);
        if ($cached !== null) {
            return $cached;
        }

        $laravelRules = [];

        foreach ($schemaDescriptor->fields as $field) {
            $targetPath = $this->toValidationPath($field->dataPath);

            foreach ($this->buildDefaultRulesForField($field, $targetPath) as $path => $rules) {
                foreach ($rules as $rule) {
                    $laravelRules[$path][] = $rule;
                }
            }

            foreach ($this->extractBaseLaravelRules($field->validationRules) as $baseRule) {
                $laravelRules[$targetPath][] = $baseRule;
            }

            $compiledRules = $this->ruleCompilerRegistry->compileForField(
                $field->fullPath,
                $targetPath,
                $field->validationRules,
                $schemaDescriptor,
            );

            foreach ($compiledRules as $rule) {
                $laravelRules[$targetPath][] = $rule;
            }
        }

        foreach ($laravelRules as $path => $rules) {
            $laravelRules[$path] = array_values(array_unique($rules, SORT_REGULAR));
        }

        $this->ruleSetCache->put($schemaId, $laravelRules);

        return $laravelRules;
    }

    private function toValidationPath(string $dataPath): string
    {
        $normalized = trim($dataPath);

        if ($normalized === '') {
            return RecordPayloadPathRegistry::ROOT_KEY;
        }

        return RecordPayloadPathRegistry::rootPath(ltrim($normalized, '.'));
    }

    /**
     * @param  array<string, mixed>  $validationRules
     * @return array<int, string>
     */
    private function extractBaseLaravelRules(array $validationRules): array
    {
        $dslKeys = $this->ruleCompilerRegistry->knownDslKeys();
        $baseRules = array_diff_key($validationRules, array_flip($dslKeys));

        if ($baseRules === []) {
            return [];
        }

        return ValidationRules::fromArray($baseRules)->toLaravelRules();
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildDefaultRulesForField(FieldDescriptor $field, string $targetPath): array
    {
        if ($field->isSystem) {
            return [];
        }

        $defaultTypeRule = $this->defaultTypeRuleForFieldType($field->type);
        $isMany = $field->cardinality === Cardinality::MANY->value;
        $hasRequiredRule = $this->hasRequiredRule($field);

        if (! $isMany) {
            $rules = [];

            if ($hasRequiredRule) {
                $rules[] = 'required';
            }

            if ($defaultTypeRule !== null) {
                $rules[] = $defaultTypeRule;
            }

            return $rules === []
                ? []
                : [$targetPath => $rules];
        }

        $containerPath = $this->manyContainerPath($targetPath);
        $elementPath = $this->manyElementPath($targetPath);

        $containerRules = ['array'];
        if ($hasRequiredRule) {
            $containerRules[] = 'present';
        }

        $result = [
            $containerPath => $containerRules,
        ];

        if ($defaultTypeRule !== null) {
            $result[$elementPath] = [$defaultTypeRule];
        }

        return $result;
    }

    private function manyContainerPath(string $targetPath): string
    {
        return str_ends_with($targetPath, '.*')
            ? substr($targetPath, 0, -2)
            : $targetPath;
    }

    private function manyElementPath(string $targetPath): string
    {
        return str_ends_with($targetPath, '.*')
            ? $targetPath
            : rtrim($targetPath, '.').'.*';
    }

    private function defaultTypeRuleForFieldType(FieldType $fieldType): string|ValidationRule|null
    {
        return match ($fieldType) {
            FieldType::STRING, FieldType::TEXT => 'string',
            FieldType::INT, FieldType::REF => 'integer',
            FieldType::MEDIA => 'ulid',
            FieldType::FLOAT => 'numeric',
            FieldType::BOOL => 'boolean',
            FieldType::DATETIME => new Iso8601DateTime,
            FieldType::JSON => 'array',
        };
    }

    private function hasRequiredRule(FieldDescriptor $field): bool
    {
        return array_key_exists('required', $field->validationRules)
            && filter_var($field->validationRules['required'], FILTER_VALIDATE_BOOL) === true;
    }
}
