<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;

/**
 * Collection of ContextFilterVO objects for filtering search contexts.
 *
 * Provides type-safe collection operations and filtering capabilities for
 * context filters used in search and completion operations.
 *
 * @method ContextFilterVO|null first()
 * @method ContextFilterVO|null last()
 * @method self filter(callable $callback)
 * @method self merge(AbstractTypedCollection $collection)
 */
final class ContextFilterVOCollection extends AbstractTypedCollection
{
    /**
     * Initializes an empty collection of ContextFilterVO items.
     */
    public function __construct()
    {
        parent::__construct(ContextFilterVO::class);
    }

    /**
     * Extracts all non-empty namespace values from the collection.
     *
     * @return string[] Array of namespace strings
     */
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

    /**
     * Extracts all cluster query values from contexts that have them.
     *
     * @return string[] Array of cluster query strings
     */
    public function getClusterQueries(): array
    {
        $queries = [];

        foreach ($this->items as $context) {
            if ($context->hasClusters()) {
                $query = $context->getClusterQuery();

                if ($query !== null) {
                    $queries[] = $query;
                }
            }
        }

        return $queries;
    }

    /**
     * Filters the collection to contexts matching the given namespace.
     *
     * @param  string  $namespace  The namespace to filter by
     * @return self A new collection containing only matching contexts
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (ContextFilterVO $context): bool => $context->namespace === $namespace
        );
    }

    /**
     * Filters the collection to contexts matching the given cluster query.
     *
     * @param  string  $clusterQuery  The cluster query string to filter by
     * @return self A new collection containing only matching contexts
     */
    public function filterByClusterQuery(string $clusterQuery): self
    {
        return $this->filter(
            fn (ContextFilterVO $context): bool => $context->getClusterQuery() === $clusterQuery
        );
    }

    /**
     * Checks if any context in the collection has cluster queries.
     *
     * @return bool True if at least one context has clusters
     */
    public function hasAnyCluster(): bool
    {
        foreach ($this->items as $context) {
            if ($context->hasClusters()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if any context in the collection has a namespace.
     *
     * @return bool True if at least one context has a namespace
     */
    public function hasAnyNamespace(): bool
    {
        foreach ($this->items as $context) {
            if ($context->hasNamespace()) {
                return true;
            }
        }

        return false;
    }
}
