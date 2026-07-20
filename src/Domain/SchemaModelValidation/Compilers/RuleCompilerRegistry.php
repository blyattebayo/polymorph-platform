<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModelValidation\Compilers;

use Polymorph\Platform\Domain\SchemaModelValidation\Schema\SchemaDescriptor;

final class RuleCompilerRegistry
{
	/**
	 * @var array<string, RuleCompilerInterface>
	 */
	private array $compilersByKey;

	public function __construct(
		FieldComparisonRuleCompiler $fieldComparison,
		RequiredIfRuleCompiler $requiredIf,
		UniqueByRuleCompiler $uniqueBy,
		QuantifierRuleCompiler $quantifier,
		InCollectionRuleCompiler $inCollection,
	) {
		$this->compilersByKey = [
			$fieldComparison->handledKey() => $fieldComparison,
			$requiredIf->handledKey() => $requiredIf,
			$uniqueBy->handledKey() => $uniqueBy,
			$quantifier->handledKey() => $quantifier,
			$inCollection->handledKey() => $inCollection,
		];
	}

	/**
	 * @return list<string>
	 */
	public function knownDslKeys(): array
	{
		return array_keys($this->compilersByKey);
	}

	/**
	 * @param array<string, mixed> $validationRules
	 * @return list<object>
	 */
	public function compileForField(
		string $fieldFullPath,
		string $targetPath,
		array $validationRules,
		SchemaDescriptor $schema,
	): array {
		$result = [];

		foreach ($this->compilersByKey as $dslKey => $compiler) {
			if (! array_key_exists($dslKey, $validationRules)) {
				continue;
			}

			foreach ($compiler->compile($fieldFullPath, $targetPath, $validationRules, $schema) as $rule) {
				$result[] = $rule;
			}
		}

		return $result;
	}
}
