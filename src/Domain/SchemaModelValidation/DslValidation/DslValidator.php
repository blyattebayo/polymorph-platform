<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\DslValidation;

use Polymorph\Platform\Domain\Records\Support\RecordPayloadPathRegistry;
use Polymorph\Platform\Domain\SchemaModelValidation\Dsl\DslOperator;
use Polymorph\Platform\Domain\SchemaModelValidation\Operands\DslOperandResolver;
use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class DslValidator
{
    private const QUANTIFIER_TYPES = ['all', 'any', 'count'];

    private const COLLECTION_MODES = ['all', 'any'];

    public function __construct(
        private readonly DslOperandResolver $operandResolver,
    ) {}

    public function validate(mixed $validationRules, SchemaDescriptor $schemaDescriptor, string $currentFieldFullPath): DslValidationResult
    {
        $result = new DslValidationResult;

        if ($validationRules === null) {
            return $result;
        }

        if (! is_array($validationRules)) {
            $result->addError('validation_rules', 'validation_rules must be an object-like map.');

            return $result;
        }

        if (array_is_list($validationRules)) {
            $result->addError('validation_rules', 'validation_rules must be an object-like map, list format is not supported.');

            return $result;
        }

        $this->validateFieldComparison($validationRules['field_comparison'] ?? null, $currentFieldFullPath, $schemaDescriptor, $result);
        $this->validateRequiredIf($validationRules['required_if'] ?? null, $currentFieldFullPath, $schemaDescriptor, $result);
        $this->validateUniqueBy($validationRules['unique_by'] ?? null, $result);
        $this->validateQuantifier($validationRules['quantifier'] ?? null, $currentFieldFullPath, $schemaDescriptor, $result);
        $this->validateInCollection($validationRules['in_collection'] ?? null, $currentFieldFullPath, $schemaDescriptor, $result);

        return $result;
    }

    private function validateFieldComparison(mixed $config, string $currentFieldFullPath, SchemaDescriptor $schemaDescriptor, DslValidationResult $result): void
    {
        if ($config === null) {
            return;
        }

        if (! is_array($config)) {
            $result->addError('validation_rules.field_comparison', 'field_comparison must be an object.');

            return;
        }

        $operator = $config['operator'] ?? null;
        if ($operator !== null && (! is_string($operator) || ! in_array($operator, DslOperator::values(), true))) {
            $result->addError('validation_rules.field_comparison.operator', 'field_comparison.operator must be one of: '.implode(', ', DslOperator::values()));
        }

        $hasField = is_string($config['field'] ?? null) && trim((string) $config['field']) !== '';
        $hasValue = array_key_exists('value', $config);

        if (! $hasField && ! $hasValue) {
            $result->addError('validation_rules.field_comparison', 'field_comparison requires either "field" or "value".');
        }

        if ($hasField) {
            $this->validateOperandReference(
                (string) $config['field'],
                $currentFieldFullPath,
                $schemaDescriptor,
                'validation_rules.field_comparison.field',
                $result,
            );
        }
    }

    private function validateRequiredIf(mixed $config, string $currentFieldFullPath, SchemaDescriptor $schemaDescriptor, DslValidationResult $result): void
    {
        if ($config === null) {
            return;
        }

        if (! is_array($config)) {
            $result->addError('validation_rules.required_if', 'required_if must be an object.');

            return;
        }

        $field = $config['field'] ?? null;
        if (! is_string($field) || trim($field) === '') {
            $result->addError('validation_rules.required_if.field', 'required_if.field must be a non-empty string operand.');

            return;
        }

        $operator = $config['operator'] ?? null;
        if ($operator !== null && (! is_string($operator) || ! in_array($operator, DslOperator::values(), true))) {
            $result->addError('validation_rules.required_if.operator', 'required_if.operator must be one of: '.implode(', ', DslOperator::values()));
        }

        $this->validateOperandReference($field, $currentFieldFullPath, $schemaDescriptor, 'validation_rules.required_if.field', $result);
    }

    private function validateUniqueBy(mixed $config, DslValidationResult $result): void
    {
        if ($config === null) {
            return;
        }

        if (is_string($config)) {
            if (trim($config) === '') {
                $result->addError('validation_rules.unique_by', 'unique_by string key cannot be empty.');
            }

            return;
        }

        if (! is_array($config)) {
            $result->addError('validation_rules.unique_by', 'unique_by must be a string or an object/list of keys.');

            return;
        }

        $rawKeys = $config['keys'] ?? $config;
        if (! is_array($rawKeys) || $rawKeys === []) {
            $result->addError('validation_rules.unique_by.keys', 'unique_by.keys must contain at least one key.');

            return;
        }

        foreach ($rawKeys as $index => $key) {
            if (! is_string($key) || trim($key) === '') {
                $result->addError("validation_rules.unique_by.keys.{$index}", 'unique_by keys must be non-empty strings.');
            }
        }
    }

    private function validateQuantifier(mixed $config, string $currentFieldFullPath, SchemaDescriptor $schemaDescriptor, DslValidationResult $result): void
    {
        if ($config === null) {
            return;
        }

        if (! is_array($config)) {
            $result->addError('validation_rules.quantifier', 'quantifier must be an object.');

            return;
        }

        $left = $config['left'] ?? null;
        if (! is_string($left) || trim($left) === '') {
            $result->addError('validation_rules.quantifier.left', 'quantifier.left must be a non-empty string operand.');

            return;
        }

        $type = $config['type'] ?? null;
        if ($type !== null && (! is_string($type) || ! in_array($type, self::QUANTIFIER_TYPES, true))) {
            $result->addError('validation_rules.quantifier.type', 'quantifier.type must be one of: '.implode(', ', self::QUANTIFIER_TYPES));
        }

        $operator = $config['operator'] ?? null;
        if ($operator !== null && (! is_string($operator) || ! in_array($operator, DslOperator::values(), true))) {
            $result->addError('validation_rules.quantifier.operator', 'quantifier.operator must be one of: '.implode(', ', DslOperator::values()));
        }

        if (array_key_exists('right', $config) && ! is_string($config['right'])) {
            $result->addError('validation_rules.quantifier.right', 'quantifier.right must be a string operand when provided.');
        }

        if (($config['type'] ?? null) === 'count' && array_key_exists('count_gte', $config)) {
            if (! is_int($config['count_gte']) || $config['count_gte'] < 1) {
                $result->addError('validation_rules.quantifier.count_gte', 'quantifier.count_gte must be an integer >= 1.');
            }
        }

        $this->validateOperandReference($left, $currentFieldFullPath, $schemaDescriptor, 'validation_rules.quantifier.left', $result);

        if (is_string($config['right'] ?? null)) {
            $this->validateOperandReference((string) $config['right'], $currentFieldFullPath, $schemaDescriptor, 'validation_rules.quantifier.right', $result);
        }
    }

    private function validateInCollection(mixed $config, string $currentFieldFullPath, SchemaDescriptor $schemaDescriptor, DslValidationResult $result): void
    {
        if ($config === null) {
            return;
        }

        if (! is_array($config)) {
            $result->addError('validation_rules.in_collection', 'in_collection must be an object.');

            return;
        }

        $source = $config['source'] ?? null;
        $inPath = $config['in'] ?? null;
        $inField = $config['in_field'] ?? null;

        if (! is_string($source) || trim($source) === '') {
            $result->addError('validation_rules.in_collection.source', 'in_collection.source must be a non-empty string operand.');
        }

        if (! is_string($inPath) || trim($inPath) === '') {
            $result->addError('validation_rules.in_collection.in', 'in_collection.in must be a non-empty string operand.');
        }

        if (! is_string($inField) || trim($inField) === '') {
            $result->addError('validation_rules.in_collection.in_field', 'in_collection.in_field must be a non-empty string operand.');
        }

        $mode = $config['mode'] ?? null;
        if ($mode !== null && (! is_string($mode) || ! in_array($mode, self::COLLECTION_MODES, true))) {
            $result->addError('validation_rules.in_collection.mode', 'in_collection.mode must be one of: '.implode(', ', self::COLLECTION_MODES));
        }

        if (is_string($source)) {
            $this->validateOperandReference($source, $currentFieldFullPath, $schemaDescriptor, 'validation_rules.in_collection.source', $result);
        }

        if (is_string($inPath)) {
            $this->validateOperandReference($inPath, $currentFieldFullPath, $schemaDescriptor, 'validation_rules.in_collection.in', $result);
        }

        if (is_string($inPath) && is_string($inField)) {
            $foreignCollection = $this->operandResolver->resolve($inPath, $currentFieldFullPath, $schemaDescriptor);

            if (! $foreignCollection->found) {
                return;
            }

            $foreignCollectionFullPath = $this->findFullPathByResolvedDataPath($foreignCollection->resolved, $schemaDescriptor);
            if ($foreignCollectionFullPath === null) {
                return;
            }

            $resolvedInField = $this->operandResolver->resolveWithinCollection($inField, $foreignCollectionFullPath, $schemaDescriptor);

            if (! $resolvedInField->found) {
                $result->addError(
                    'validation_rules.in_collection.in_field',
                    sprintf('in_collection.in_field points to unknown schema path "%s".', $this->stripDataJsonPrefix($resolvedInField->resolved)),
                );
            }
        }
    }

    private function validateOperandReference(
        string $operand,
        string $currentFieldFullPath,
        SchemaDescriptor $schemaDescriptor,
        string $errorPath,
        DslValidationResult $result,
    ): void {
        $resolved = $this->operandResolver->resolve($operand, $currentFieldFullPath, $schemaDescriptor);

        if (trim($resolved->resolved) === '') {
            return;
        }

        if (! $resolved->found) {
            $result->addError($errorPath, sprintf('Operand "%s" points to unknown schema path "%s".', $operand, $this->stripDataJsonPrefix($resolved->resolved)));
        }
    }

    private function stripDataJsonPrefix(string $resolvedPath): string
    {
        return RecordPayloadPathRegistry::stripDottedPrefix($resolvedPath);
    }

    private function findFullPathByResolvedDataPath(string $resolvedDataPath, SchemaDescriptor $schemaDescriptor): ?string
    {
        $normalized = $this->stripDataJsonPrefix($resolvedDataPath);

        foreach ($schemaDescriptor->pathIndex as $fullPath => $dataPath) {
            if ($dataPath === $normalized) {
                return $fullPath;
            }
        }

        return null;
    }
}
