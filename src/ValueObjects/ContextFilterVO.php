<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use InvalidArgumentException;

/**
 * Value Object representing a context filter for document queries.
 *
 * Encapsulates namespace and cluster filtering criteria for token searches.
 * At least one of namespace or clusters must be provided.
 *
 * @example
 * // Simple namespace filter
 * $context = new ContextFilterVO('App.Models.User');
 *
 * // Single cluster
 * $clusters = (new ClusterVOCollection())->add(new ClusterVO('tenant:company_abc@AND'));
 * $context = new ContextFilterVO('App.Models.User', $clusters);
 *
 * // Multiple clusters with operator
 * $clusters = (new ClusterVOCollection())
 *     ->add(new ClusterVO('tenant:company_abc@AND'))
 *     ->add(new ClusterVO('env:production@AND'));
 * $context = new ContextFilterVO('App.Models.User', $clusters, 'AND');
 *
 * // Clusters only (no namespace)
 * $clusters = (new ClusterVOCollection())->add(new ClusterVO('status:active@AND'));
 * $context = new ContextFilterVO(null, $clusters, 'AND');
 */
final class ContextFilterVO extends AbstractValueObject
{
    public readonly ?string $namespace;

    public readonly ?ClusterVOCollection $clusters;

    public readonly string $clustersOperator;

    /**
     * @param  string|null  $namespace  The namespace to filter on (e.g., 'App.Models.User')
     * @param  ClusterVOCollection|null  $clusters  Collection of clusters to filter on
     * @param  string  $clustersOperator  Operator between clusters: 'AND', 'OR', 'NOT' (default: 'AND')
     *
     * @throws InvalidArgumentException If both namespace and clusters are null or empty
     */
    public function __construct(
        ?string $namespace = null,
        ?ClusterVOCollection $clusters = null,
        string $clustersOperator = 'AND'
    ) {
        if ($namespace === null && ($clusters === null || $clusters->isEmpty())) {
            throw new InvalidArgumentException('At least one of namespace or clusters must be provided');
        }

        $this->namespace = $namespace;
        $this->clusters = $clusters;
        $this->clustersOperator = $clustersOperator;
    }

    /**
     * Checks if a namespace filter is set.
     */
    public function hasNamespace(): bool
    {
        return $this->namespace !== null;
    }

    /**
     * Checks if cluster filters are set.
     */
    public function hasClusters(): bool
    {
        return $this->clusters !== null && ! $this->clusters->isEmpty();
    }

    /**
     * Returns the filter value as a StrictAssociative array.
     *
     * Only includes non-null values.
     *
     * @return StrictAssociative<string, mixed>
     */
    public function getValue(): StrictAssociative
    {
        $data = [];

        if ($this->namespace !== null) {
            $data['namespace'] = $this->namespace;
        }

        if ($this->hasClusters()) {
            $data['clusters'] = $this->clusters->map(fn ($c) => $c->getValue())->toArray();
            $data['clusters_operator'] = $this->clustersOperator;
        }

        return StrictAssociative::from($data);
    }

    /**
     * Get clusters as string for backward compatibility.
     *
     * @deprecated Use getValue() instead
     */
    public function getClusterString(): ?string
    {
        if (! $this->hasClusters()) {
            return null;
        }

        $clusterStrings = [];
        foreach ($this->clusters as $cluster) {
            $clusterStrings[] = $cluster->getValue();
        }

        return implode('|', $clusterStrings);
    }
}
