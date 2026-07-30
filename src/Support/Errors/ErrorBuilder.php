<?php

declare(strict_types=1);

namespace Polymorph\Platform\Support\Errors;

/**
 * Накопитель полей будущего ErrorPayload.
 *
 * Инварианты (диапазон статуса, непустые строки, форма meta) проверяет только
 * конструктор ErrorPayload — единственная точка, через которую проходит каждый
 * payload. Раньше те же ассерты жили ещё и здесь, и копии уже разошлись:
 * билдер браковал meta-ключ из пробелов, payload — только пустую строку.
 */
final class ErrorBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $meta = [];

    private string $detail;

    private string $uri;

    private string $title;

    private int $status;

    private ?string $traceId = null;

    public function __construct(private readonly ErrorType $type)
    {
        $this->uri = $type->uri;
        $this->title = $type->title;
        $this->status = $type->status;
        $this->detail = $type->defaultDetail;
    }

    public function detail(string $detail): self
    {
        $clone = clone $this;
        $clone->detail = $detail;

        return $clone;
    }

    public function title(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function status(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        $clone = clone $this;
        $clone->meta = $meta;

        return $clone;
    }

    public function addMeta(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->meta[$key] = $value;

        return $clone;
    }

    public function build(): ErrorPayload
    {
        return ErrorPayload::create(
            type: $this->uri,
            title: $this->title,
            status: $this->status,
            code: $this->type->code,
            detail: $this->detail,
            meta: $this->meta,
            traceId: $this->traceId,
        );
    }
}
