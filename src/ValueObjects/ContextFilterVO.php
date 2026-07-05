<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use InvalidArgumentException;

/**
 * Value Object representing a context filter for document queries.
 *
 * Encapsulates namespace and cluster filtering criteria for token searches.
 * At least one of namespace or cluster must be provided.
 *
 * @example
 * $context = new ContextFilterVO('App.Models.User', 'tenant:company_abc');
 * $context->hasNamespace(); // true
 * $context->hasCluster(); // true
 * $context->getValue(); // ['namespace' => 'App.Models.User', 'cluster' => 'tenant:company_abc']
 */
final class ContextFilterVO extends AbstractValueObject
{
    public readonly ?string $namespace;

    public readonly ?string $cluster;

    /**
     * @param  string|null  $namespace  The namespace to filter on (e.g., 'App.Models.User')
     * @param  string|null  $cluster  The cluster to filter on (e.g., 'tenant:company_abc')
     *
     * @throws InvalidArgumentException If both namespace and cluster are null
     */
    public function __construct(?string $namespace = null, ?string $cluster = null)
    {
        if ($namespace === null && $cluster === null) {
            throw new InvalidArgumentException('At least one of namespace or cluster must be provided');
        }

        $this->namespace = $namespace;
        $this->cluster = $cluster;
    }

    /**
     * Checks if a namespace filter is set.
     */
    public function hasNamespace(): bool
    {
        return $this->namespace !== null;
    }

    /**
     * Checks if a cluster filter is set.
     */
    public function hasCluster(): bool
    {
        return $this->cluster !== null;
    }

    /**
     * Returns the filter value as a StrictAssociative array.
     *
     * Only includes non-null values.
     *
     * @return StrictAssociative<string, string>
     */
    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from(array_filter([
            'namespace' => $this->namespace,
            'cluster' => $this->cluster,
        ]));
    }
}
