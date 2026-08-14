<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Projection;

use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldType;
use Polymorph\Platform\Domain\DataPlatform\Schema\SchemaCatalog;
use Polymorph\Platform\Domain\DataPlatform\Validation\DataValidationException;
use Polymorph\Platform\TemplateEngine\Core\AST\ExpressionNode;
use Polymorph\Platform\TemplateEngine\Core\AST\FieldNode;
use Polymorph\Platform\TemplateEngine\Core\AST\RefNode;
use Polymorph\Platform\TemplateEngine\Core\Errors\LexerException;
use Polymorph\Platform\TemplateEngine\Core\Errors\ParserException;
use Polymorph\Platform\TemplateEngine\Core\Errors\ValidationException;
use Polymorph\Platform\TemplateEngine\Core\Pipeline\TemplateParsePipeline;

/** Compiles display templates against stable fields before metadata can be saved. */
final class DisplayTemplateCompiler
{
    public function __construct(
        private readonly TemplateParsePipeline $templates,
        private readonly SchemaCatalog $schemas,
    ) {}

    public function compile(int $definitionId, ?string $source): CompiledDisplayTemplate
    {
        $source = trim((string) $source);
        if ($source === '') {
            return new CompiledDisplayTemplate('', hash('sha256', ''));
        }

        try {
            $ast = $this->templates->parseAndValidate($source);
        } catch (LexerException|ParserException|ValidationException $exception) {
            throw DataValidationException::one(
                'invalid_display_template',
                'Display template compilation failed.',
                'display_template',
                meta: ['compiler_error' => $exception->getMessage()],
            );
        }

        foreach ($ast->children as $child) {
            if ($child instanceof ExpressionNode) {
                $this->validateExpression($definitionId, $child);
            }
        }

        return new CompiledDisplayTemplate($source, hash('sha256', $source));
    }

    private function validateExpression(int $definitionId, ExpressionNode $expression): void
    {
        $activeDefinitionId = $definitionId;
        $referenceDepth = 0;
        foreach ([$expression->path->head, ...$expression->path->segments] as $segment) {
            if (! $segment instanceof FieldNode && ! $segment instanceof RefNode) {
                continue;
            }

            $field = $this->field($activeDefinitionId, $segment->fieldId);
            if (! $segment instanceof RefNode) {
                continue;
            }

            $referenceDepth++;
            $maximumDepth = max(0, (int) config('data_platform.display.max_ref_depth'));
            if ($referenceDepth > $maximumDepth) {
                throw DataValidationException::one(
                    'display_template_reference_depth_exceeded',
                    'Display template reference depth exceeds the configured limit.',
                    'display_template',
                    meta: ['depth' => $referenceDepth, 'maximum' => $maximumDepth],
                );
            }
            if ($field->type !== FieldType::REF) {
                throw DataValidationException::one(
                    'display_template_ref_requires_reference_field',
                    "Display ref '{$field->id}' points to a non-reference field.",
                    'display_template',
                    meta: ['field_id' => $field->id],
                );
            }
            $targets = $field->constraints['allowed_record_definition_ids'] ?? [];
            if (! is_array($targets) || count($targets) !== 1) {
                throw DataValidationException::one(
                    'display_template_ref_requires_single_target',
                    'A display ref requires exactly one allowed target definition.',
                    'display_template',
                    meta: ['field_id' => $field->id],
                );
            }
            $activeDefinitionId = (int) $targets[0];
        }
    }

    private function field(int $definitionId, string $fieldId): FieldDefinition
    {
        foreach ($this->schemas->writableDefinition($definitionId)['fields'] as $field) {
            if ($field->id === $fieldId) {
                return $field;
            }
        }

        throw DataValidationException::one(
            'display_template_unknown_field',
            "Display template references unknown field '{$fieldId}'.",
            'display_template',
            meta: ['field_id' => $fieldId, 'record_definition_id' => $definitionId],
        );
    }
}
