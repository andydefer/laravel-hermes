<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;

/**
 * @method SearchResultRecord|null first()
 * @method SearchResultRecord|null last()
 * @method SearchResultRecord|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 */
final class SearchResultRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchResultRecord::class);
    }

    public function getDocumentIds(): array
    {
        return array_map(fn (SearchResultRecord $r) => $r->document_id, $this->items);
    }

    public function getFingerprints(): array
    {
        return array_map(fn (SearchResultRecord $r) => $r->fingerprint, $this->items);
    }

    public function filterByMinSimilarity(float $minSimilarity): self
    {
        return $this->filter(fn (SearchResultRecord $r) => $r->similarity >= $minSimilarity);
    }

    public function getData(): array
    {
        return array_map(fn (SearchResultRecord $r) => $r->data->toArray(), $this->items);
    }
}
