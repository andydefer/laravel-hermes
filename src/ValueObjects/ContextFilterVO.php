<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
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
 * // Single cluster query
 * $context = new ContextFilterVO(
 *     'App.Models.User',
 *     new ClusterQueries(['cluster' => 'tenant=company_abc'])
 * );
 *
 * // Multiple cluster queries
 * $context = new ContextFilterVO(
 *     'App.Models.User',
 *     new ClusterQueries([
 *         'cluster' => 'tenant=company_abc',
 *         'metadata' => 'env=production'
 *     ])
 * );
 *
 * // Clusters only (no namespace)
 * $context = new ContextFilterVO(
 *     null,
 *     new ClusterQueries(['cluster' => 'status=active'])
 * );
 */
final class ContextFilterVO extends AbstractValueObject
{
    public readonly ?string $namespace;

    public readonly ?ClusterQueries $clusterQueries;

    /**
     * @param  string|null  $namespace  The namespace to filter on (e.g., 'App\Models\User')
     * @param  ClusterQueries|null  $clusterQueries  The cluster queries to filter on
     *
     * @throws InvalidArgumentException If both namespace and clusterQueries are null or empty
     */
    public function __construct(
        ?string $namespace = null,
        ?ClusterQueries $clusterQueries = null
    ) {
        if ($namespace === null && ($clusterQueries === null || $clusterQueries->isEmpty())) {
            throw new InvalidArgumentException('At least one of namespace or clusterQueries must be provided');
        }

        $this->namespace = $namespace;
        $this->clusterQueries = $clusterQueries;
    }

    /**
     * Checks if a namespace filter is set.
     */
    public function hasNamespace(): bool
    {
        return $this->namespace !== null;
    }

    /**
     * Checks if cluster queries are set.
     */
    public function hasClusters(): bool
    {
        return $this->clusterQueries !== null && ! $this->clusterQueries->isEmpty();
    }

    /**
     * Returns the column name for cluster queries.
     */
    public function getClusterColumn(): string
    {
        return 'cluster';
    }

    /**
     * Returns the cluster query expression.
     *
     * @return string|null The cluster query, or null if none set
     */
    public function getClusterQuery(): ?string
    {
        if (! $this->hasClusters()) {
            return null;
        }

        $queries = $this->clusterQueries->all();

        // Return the first query if only one column is used
        if (count($queries) === 1) {
            return reset($queries);
        }

        // Combine multiple queries with AND
        return implode(' & ', $queries);
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
            $data['cluster_queries'] = $this->clusterQueries->all();
        }

        return StrictAssociative::from($data);
    }
}
