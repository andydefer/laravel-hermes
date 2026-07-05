<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelHermes\Records\CompletionResultRecord;

/**
 * @method CompletionResultRecord|null first()
 * @method CompletionResultRecord|null last()
 * @method CompletionResultRecord|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 */
final class CompletionResultRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(CompletionResultRecord::class);
    }

    public function getTokens(): array
    {
        return array_map(fn (CompletionResultRecord $r) => $r->token, $this->items);
    }

    public function getOriginalTexts(): array
    {
        return array_map(fn (CompletionResultRecord $r) => $r->original_text, $this->items);
    }

    public function getIds(): array
    {
        return array_map(fn (CompletionResultRecord $r) => $r->token_id, $this->items);
    }

    public function getDocumentIds(): array
    {
        return array_map(fn (CompletionResultRecord $r) => $r->document_id, $this->items);
    }

    public function filterByField(string $field): self
    {
        return $this->filter(fn (CompletionResultRecord $r) => $r->field === $field);
    }

    public function filterByMinSimilarity(float $minSimilarity): self
    {
        return $this->filter(fn (CompletionResultRecord $r) => $r->similarity >= $minSimilarity);
    }
}
