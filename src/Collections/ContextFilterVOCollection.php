<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;

/**
 * @method ContextFilterVO|null first()
 * @method ContextFilterVO|null last()
 * @method self filter(callable $callback)
 * @method self merge(AbstractTypedCollection $collection)
 */
final class ContextFilterVOCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ContextFilterVO::class);
    }

    public function getNamespaces(): array
    {
        $namespaces = [];
        foreach ($this->items as $context) {
            if ($context->hasNamespace()) {
                $namespaces[] = $context->namespace;
            }
        }

        return $namespaces;
    }

    public function getClusters(): array
    {
        $clusters = [];
        foreach ($this->items as $context) {
            if ($context->hasCluster()) {
                $clusters[] = $context->cluster;
            }
        }

        return $clusters;
    }

    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (ContextFilterVO $context) => $context->namespace === $namespace
        );
    }

    public function filterByCluster(string $cluster): self
    {
        return $this->filter(
            fn (ContextFilterVO $context) => $context->cluster === $cluster
        );
    }
}
