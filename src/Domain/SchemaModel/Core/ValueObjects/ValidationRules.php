<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects;

/**
 * Value Object для правил валидации поля.
 *
 * Инкапсулирует логику работы с validation_rules.
 */
final readonly class ValidationRules
{
    /**
     * @param  array<string, mixed>  $rules
     */
    private function __construct(
        private array $rules
    ) {}

    /**
     * Создать из массива.
     *
     * @param  array<string, mixed>  $rules
     */
    public static function fromArray(array $rules): self
    {
        return new self($rules);
    }

    /**
     * Получить все правила как массив.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rules;
    }

    /**
     * Конвертировать в Laravel validation rules.
     *
     * @return string[]
     */
    public function toLaravelRules(): array
    {
        $laravelRules = [];
        foreach ($this->rules as $key => $value) {
            if (is_bool($value)) {
                if ($value === true) {
                    $laravelRules[] = $key;
                }

                continue;
            }
            $laravelRules[] = match ($key) {
                'required' => 'required',
                'nullable' => 'nullable',
                'string' => 'string',
                'integer' => 'integer',
                'boolean' => 'boolean',
                'date' => 'date',
                'array' => 'array',
                'numeric' => 'numeric',
                'min' => "min:{$value}",
                'max' => "max:{$value}",
                'email' => 'email',
                'url' => 'url',
                'regex' => "regex:{$value}",
                'in' => 'in:'.implode(',', (array) $value),
                'unique' => "unique:{$value}",
                default => "{$key}:{$value}",
            };
        }

        return $laravelRules;
    }
}
