<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Collections;

use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use InvalidArgumentException;

final class ContextFilterVOCollectionTest extends IntegrationTestCase
{
    private ContextFilterVOCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = new ContextFilterVOCollection;
    }

    private function createNamespaceContext(string $namespace): ContextFilterVO
    {
        return new ContextFilterVO($namespace);
    }

    private function createClusterContext(array $queries): ContextFilterVO
    {
        return new ContextFilterVO(
            null,
            new ClusterQueries($queries)
        );
    }

    private function createFullContext(string $namespace, array $queries): ContextFilterVO
    {
        return new ContextFilterVO(
            $namespace,
            new ClusterQueries($queries)
        );
    }

    // ============================================================
    // TESTS DE CONSTRUCTION ET AJOUT
    // ============================================================

    public function test_can_add_items_to_collection(): void
    {
        $context1 = $this->createNamespaceContext('App\\Models\\User');
        $context2 = $this->createClusterContext(['cluster' => 'status=active']);

        $this->collection->add($context1);
        $this->collection->add($context2);

        $this->assertCount(2, $this->collection);
        $this->assertSame($context1, $this->collection->first());
        $this->assertSame($context2, $this->collection->last());
    }

    public function test_can_add_multiple_items_at_once(): void
    {
        $context1 = $this->createNamespaceContext('App\\Models\\User');
        $context2 = $this->createNamespaceContext('App\\Models\\Product');
        $context3 = $this->createClusterContext(['cluster' => 'status=active']);

        $this->collection->add($context1, $context2, $context3);

        $this->assertCount(3, $this->collection);
    }

    public function test_throws_exception_when_adding_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->collection->add('invalid');
    }

    public function test_can_be_created_empty(): void
    {
        $collection = new ContextFilterVOCollection;

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    // ============================================================
    // TESTS D'EXTRACTION DE DONNÉES
    // ============================================================

    public function test_can_get_namespaces(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User'),
            $this->createNamespaceContext('App\\Models\\Product'),
            $this->createClusterContext(['cluster' => 'status=active'])
        );

        $namespaces = $this->collection->getNamespaces();

        $this->assertCount(2, $namespaces);
        $this->assertContains('App\\Models\\User', $namespaces);
        $this->assertContains('App\\Models\\Product', $namespaces);
        $this->assertNotContains(null, $namespaces);
    }

    public function test_get_namespaces_ignores_contexts_without_namespace(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User'),
            $this->createClusterContext(['cluster' => 'status=active']),
            $this->createClusterContext(['cluster' => 'tenant=company_abc'])
        );

        $namespaces = $this->collection->getNamespaces();

        $this->assertCount(1, $namespaces);
        $this->assertContains('App\\Models\\User', $namespaces);
    }

    public function test_get_namespaces_returns_empty_array_when_no_namespaces(): void
    {
        $this->collection->add(
            $this->createClusterContext(['cluster' => 'status=active']),
            $this->createClusterContext(['cluster' => 'tenant=company_abc'])
        );

        $namespaces = $this->collection->getNamespaces();

        $this->assertEmpty($namespaces);
    }

    public function test_can_get_cluster_queries(): void
    {
        $this->collection->add(
            $this->createClusterContext(['cluster' => 'tenant=company_abc']),
            $this->createClusterContext(['cluster' => 'status=active']),
            $this->createNamespaceContext('App\\Models\\User')
        );

        $queries = $this->collection->getClusterQueries();

        $this->assertCount(2, $queries);
        $this->assertContains('tenant=company_abc', $queries);
        $this->assertContains('status=active', $queries);
    }

    public function test_get_cluster_queries_ignores_contexts_without_clusters(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User'),
            $this->createClusterContext(['cluster' => 'status=active']),
            $this->createNamespaceContext('App\\Models\\Product')
        );

        $queries = $this->collection->getClusterQueries();

        $this->assertCount(1, $queries);
        $this->assertContains('status=active', $queries);
    }

    public function test_get_cluster_queries_handles_multiple_queries(): void
    {
        $this->collection->add(
            $this->createClusterContext([
                'cluster' => 'tenant=company_abc',
                'metadata' => 'status=active',
            ])
        );

        $queries = $this->collection->getClusterQueries();

        $this->assertCount(1, $queries);
        $this->assertEquals('tenant=company_abc & status=active', $queries[0]);
    }

    public function test_get_cluster_queries_returns_empty_array_when_no_clusters(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User'),
            $this->createNamespaceContext('App\\Models\\Product')
        );

        $queries = $this->collection->getClusterQueries();

        $this->assertEmpty($queries);
    }

    // ============================================================
    // TESTS DE FILTRAGE
    // ============================================================

    public function test_can_filter_by_namespace(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User'),
            $this->createNamespaceContext('App\\Models\\Product'),
            $this->createNamespaceContext('App\\Models\\User')
        );

        $filtered = $this->collection->filterByNamespace('App\\Models\\User');

        $this->assertCount(2, $filtered);
        $this->assertNotSame($filtered, $this->collection);

        foreach ($filtered as $context) {
            $this->assertEquals('App\\Models\\User', $context->namespace);
        }
    }

    public function test_filter_by_namespace_returns_empty_when_no_match(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\Product'),
            $this->createNamespaceContext('App\\Models\\Order')
        );

        $filtered = $this->collection->filterByNamespace('App\\Models\\User');

        $this->assertCount(0, $filtered);
        $this->assertTrue($filtered->isEmpty());
    }

    public function test_filter_by_namespace_ignores_contexts_without_namespace(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User'),
            $this->createClusterContext(['cluster' => 'status=active'])
        );

        $filtered = $this->collection->filterByNamespace('App\\Models\\User');

        $this->assertCount(1, $filtered);
        $this->assertEquals('App\\Models\\User', $filtered->first()->namespace);
    }

    public function test_can_filter_by_cluster_query(): void
    {
        $this->collection->add(
            $this->createClusterContext(['cluster' => 'tenant=company_abc']),
            $this->createClusterContext(['cluster' => 'status=active']),
            $this->createClusterContext(['cluster' => 'tenant=company_abc'])
        );

        $filtered = $this->collection->filterByClusterQuery('tenant=company_abc');

        $this->assertCount(2, $filtered);
        $this->assertNotSame($filtered, $this->collection);

        foreach ($filtered as $context) {
            $this->assertEquals('tenant=company_abc', $context->getClusterQuery());
        }
    }

    public function test_filter_by_cluster_query_returns_empty_when_no_match(): void
    {
        $this->collection->add(
            $this->createClusterContext(['cluster' => 'status=active']),
            $this->createClusterContext(['cluster' => 'env=production'])
        );

        $filtered = $this->collection->filterByClusterQuery('tenant=company_abc');

        $this->assertCount(0, $filtered);
        $this->assertTrue($filtered->isEmpty());
    }

    public function test_filter_by_cluster_query_ignores_contexts_without_clusters(): void
    {
        $this->collection->add(
            $this->createClusterContext(['cluster' => 'tenant=company_abc']),
            $this->createNamespaceContext('App\\Models\\User')
        );

        $filtered = $this->collection->filterByClusterQuery('tenant=company_abc');

        $this->assertCount(1, $filtered);
        $this->assertEquals('tenant=company_abc', $filtered->first()->getClusterQuery());
    }

    public function test_can_chain_filters(): void
    {
        $this->collection->add(
            $this->createFullContext('App\\Models\\User', ['cluster' => 'tenant=company_abc']),
            $this->createFullContext('App\\Models\\User', ['cluster' => 'tenant=company_xyz']),
            $this->createFullContext('App\\Models\\Product', ['cluster' => 'tenant=company_abc']),
            $this->createFullContext('App\\Models\\User', ['cluster' => 'tenant=company_abc'])
        );

        $filtered = $this->collection
            ->filterByNamespace('App\\Models\\User')
            ->filterByClusterQuery('tenant=company_abc');

        $this->assertCount(2, $filtered);

        foreach ($filtered as $context) {
            $this->assertEquals('App\\Models\\User', $context->namespace);
            $this->assertEquals('tenant=company_abc', $context->getClusterQuery());
        }
    }

    // ============================================================
    // TESTS DE VÉRIFICATION (HAS ANY)
    // ============================================================

    public function test_has_any_cluster_returns_true_when_clusters_exist(): void
    {
        $this->collection->add(
            $this->createClusterContext(['cluster' => 'status=active'])
        );

        $this->assertTrue($this->collection->hasAnyCluster());
    }

    public function test_has_any_cluster_returns_false_when_no_clusters(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User')
        );

        $this->assertFalse($this->collection->hasAnyCluster());
    }

    public function test_has_any_cluster_returns_false_when_empty(): void
    {
        $this->assertFalse($this->collection->hasAnyCluster());
    }

    public function test_has_any_cluster_returns_true_with_mixed_contexts(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User'),
            $this->createClusterContext(['cluster' => 'status=active'])
        );

        $this->assertTrue($this->collection->hasAnyCluster());
    }

    public function test_has_any_namespace_returns_true_when_namespaces_exist(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User')
        );

        $this->assertTrue($this->collection->hasAnyNamespace());
    }

    public function test_has_any_namespace_returns_false_when_no_namespaces(): void
    {
        $this->collection->add(
            $this->createClusterContext(['cluster' => 'status=active'])
        );

        $this->assertFalse($this->collection->hasAnyNamespace());
    }

    public function test_has_any_namespace_returns_false_when_empty(): void
    {
        $this->assertFalse($this->collection->hasAnyNamespace());
    }

    public function test_has_any_namespace_returns_true_with_mixed_contexts(): void
    {
        $this->collection->add(
            $this->createClusterContext(['cluster' => 'status=active']),
            $this->createNamespaceContext('App\\Models\\User')
        );

        $this->assertTrue($this->collection->hasAnyNamespace());
    }

    // ============================================================
    // TESTS DE COLLECTION VIDE
    // ============================================================

    public function test_get_namespaces_returns_empty_array_when_empty(): void
    {
        $namespaces = $this->collection->getNamespaces();

        $this->assertEmpty($namespaces);
    }

    public function test_get_cluster_queries_returns_empty_array_when_empty(): void
    {
        $queries = $this->collection->getClusterQueries();

        $this->assertEmpty($queries);
    }

    // ============================================================
    // TESTS D'IMMUTABILITÉ
    // ============================================================

    public function test_filters_do_not_modify_original_collection(): void
    {
        $original = new ContextFilterVOCollection;
        $original->add($this->createNamespaceContext('App\\Models\\User'));
        $original->add($this->createNamespaceContext('App\\Models\\Product'));

        $filtered = $original->filterByNamespace('App\\Models\\User');

        $this->assertCount(2, $original);
        $this->assertCount(1, $filtered);
        $this->assertNotSame($filtered, $original);
    }

    // ============================================================
    // TESTS DE TYPE DE RETOUR
    // ============================================================

    public function test_filter_returns_same_type(): void
    {
        $filtered = $this->collection->filterByNamespace('App\\Models\\User');

        $this->assertInstanceOf(ContextFilterVOCollection::class, $filtered);
    }

    public function test_filter_by_cluster_query_returns_same_type(): void
    {
        $filtered = $this->collection->filterByClusterQuery('tenant=company_abc');

        $this->assertInstanceOf(ContextFilterVOCollection::class, $filtered);
    }

    // ============================================================
    // TESTS DE CAS COMPLEXES
    // ============================================================

    public function test_can_handle_mixed_contexts(): void
    {
        $this->collection->add(
            $this->createNamespaceContext('App\\Models\\User'),
            $this->createClusterContext(['cluster' => 'status=active']),
            $this->createFullContext('App\\Models\\Product', ['cluster' => 'tenant=company_abc']),
            $this->createClusterContext(['cluster' => 'env=production'])
        );

        $namespaces = $this->collection->getNamespaces();
        $queries = $this->collection->getClusterQueries();

        $this->assertCount(2, $namespaces);
        $this->assertContains('App\\Models\\User', $namespaces);
        $this->assertContains('App\\Models\\Product', $namespaces);

        $this->assertCount(3, $queries);
        $this->assertContains('status=active', $queries);
        $this->assertContains('tenant=company_abc', $queries);
        $this->assertContains('env=production', $queries);

        $this->assertTrue($this->collection->hasAnyNamespace());
        $this->assertTrue($this->collection->hasAnyCluster());
    }

    public function test_can_filter_contexts_with_both_namespace_and_cluster(): void
    {
        $this->collection->add(
            $this->createFullContext('App\\Models\\User', ['cluster' => 'tenant=company_abc']),
            $this->createFullContext('App\\Models\\User', ['cluster' => 'tenant=company_xyz']),
            $this->createFullContext('App\\Models\\Product', ['cluster' => 'tenant=company_abc'])
        );

        $filtered = $this->collection
            ->filterByNamespace('App\\Models\\User')
            ->filterByClusterQuery('tenant=company_abc');

        $this->assertCount(1, $filtered);

        $context = $filtered->first();
        $this->assertEquals('App\\Models\\User', $context->namespace);
        $this->assertEquals('tenant=company_abc', $context->getClusterQuery());
    }

    // ============================================================
    // TESTS DE PERFORMANCE ET VOLUME
    // ============================================================

    public function test_can_handle_large_collection(): void
    {
        $count = 500;

        for ($i = 0; $i < $count; $i++) {
            $context = $i % 3 === 0
                ? $this->createNamespaceContext("App\\Models\\Model{$i}")
                : $this->createClusterContext(['cluster' => "tenant=company_{$i}"]);

            $this->collection->add($context);
        }

        $this->assertCount($count, $this->collection);

        $namespaces = $this->collection->getNamespaces();
        $queries = $this->collection->getClusterQueries();

        // Environ un tiers des éléments sont des namespaces
        $this->assertGreaterThan(100, $namespaces);
        $this->assertGreaterThan(300, $queries);

        $filtered = $this->collection->filterByNamespace('App\\Models\\Model0');
        $this->assertCount(1, $filtered);

        $this->assertTrue($this->collection->hasAnyNamespace());
        $this->assertTrue($this->collection->hasAnyCluster());
    }
}
