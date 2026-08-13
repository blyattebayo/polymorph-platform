<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DisplayViews\Services;

use Polymorph\Platform\Domain\DisplayViews\Exceptions\InvalidDisplayTemplateException;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshot;
use Polymorph\Platform\TemplateEngine\Core\Errors\LexerException;
use Polymorph\Platform\TemplateEngine\Core\Errors\ParserException;
use Polymorph\Platform\TemplateEngine\Core\Errors\ValidationException as TemplateValidationException;
use Polymorph\Platform\TemplateEngine\Core\Pipeline\TemplateParsePipeline;

/** Compiles and validates the exact display-template language used by SQL views. */
final class DisplayTemplateCompiler
{
    public function __construct(
        private readonly TemplateParsePipeline $templateParsePipeline,
        private readonly SqlDisplayViewCompiler $sqlCompiler,
    ) {}

    /** @return array{expression:string,template_hash:string} */
    public function compile(int $recordDefinitionId, ?string $templateSource, SchemaSnapshot $schema): array
    {
        try {
            if ($templateSource === null || trim($templateSource) === '') {
                return [
                    'expression' => "('Record #' || src.id::text)",
                    'template_hash' => hash('sha256', ''),
                ];
            }

            $ast = $this->templateParsePipeline->parseAndValidate($templateSource);

            return $this->sqlCompiler->compile($templateSource, $ast, $schema);
        } catch (LexerException|ParserException|TemplateValidationException $exception) {
            throw InvalidDisplayTemplateException::forRecordDefinition($recordDefinitionId, $exception);
        }
    }
}
