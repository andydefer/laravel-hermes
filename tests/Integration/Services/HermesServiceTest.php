<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Services;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelHermes\Collections\CompletionResultRecordCollection;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelHermes\Collections\SuggestionResultRecordCollection;
use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelHermes\Records\MatchRecord;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

final class HermesServiceTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private HermesInterface $hermes;

    private IndexerInterface $indexer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hermes = $this->app->make(HermesInterface::class);
        $this->indexer = $this->app->make(IndexerInterface::class);
    }

    private function createAndIndexUser(int $id, string $name, string $email, string $description = '', array $cluster = ['tenant' => 'company_abc']): void
    {
        $user = TestUser::create([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'description' => $description,
            'is_active' => true,
        ]);

        $clusterVO = new ClusterVO($cluster);
        $record = IndexableRecordFactory::convert($user, $clusterVO);
        $this->indexer->index($record);
    }

    private function createAndIndexProduct(int $id, string $name, string $reference, string $description = '', array $cluster = ['tenant' => 'company_abc']): void
    {
        $product = TestProduct::create([
            'id' => $id,
            'name' => $name,
            'reference' => $reference,
            'description' => $description,
            'is_published' => true,
        ]);

        $clusterVO = new ClusterVO($cluster);
        $record = IndexableRecordFactory::convert($product, $clusterVO);
        $this->indexer->index($record);
    }

    private function createContextFilterForNamespace(string $namespace): ContextFilterVO
    {
        return new ContextFilterVO($namespace);
    }

    private function createContextFilterForCluster(array $queries, ?string $namespace = null): ContextFilterVO
    {
        return new ContextFilterVO(
            $namespace,
            new ClusterQueries($queries)
        );
    }

    // ==================== COMPLETION TESTS ====================

    public function test_complete_returns_completions(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $request = CompletionRequestRecord::from([
            'query' => 'joh=name',
            'limit' => 10,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertInstanceOf(CompletionResultRecordCollection::class, $results);
        $this->assertNotEmpty($results);

        $first = $results->first();
        $this->assertEquals('John', $first->original_text);
        $this->assertEquals('name', $first->field);
        $this->assertGreaterThan(0, $first->similarity);
    }

    public function test_complete_with_multiple_ngrams(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com');

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO('joh=name|jan=name'),
            'limit' => 10,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
    }

    public function test_complete_with_fields_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO('joh=name,email'),
            'limit' => 10,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertContains($result->field, ['name', 'email']);
        }
    }

    public function test_complete_with_namespace_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $this->createAndIndexProduct(1, 'Product X', 'REF-001');

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForNamespace(TestUser::class));

        $request = CompletionRequestRecord::from([
            'query' => 'joh=name',
            'limit' => 10,
            'contexts' => $contexts,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertEquals('John', $result->original_text);
        }
    }

    public function test_complete_with_cluster_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: ['tenant' => 'company_abc']);
        $this->createAndIndexUser(2, 'Johnny Cash', 'johnny@example.com', cluster: ['tenant' => 'company_xyz']);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_abc',
        ]));

        $request = CompletionRequestRecord::from([
            'query' => 'joh=name',
            'limit' => 10,
            'contexts' => $contexts,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->original_text);
    }

    public function test_complete_with_cluster_filter_and_condition_email(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john.doe@example.com', cluster: ['tenant' => 'company_abc', 'status' => 'active']);
        $this->createAndIndexUser(2, 'John Smith', 'john.smith@example.com', cluster: ['tenant' => 'company_abc', 'status' => 'inactive']);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_abc & status=active',
        ]));

        $request = CompletionRequestRecord::from([
            'query' => 'john=email',
            'limit' => 10,
            'contexts' => $contexts,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
        $this->assertCount(1, $results);

        $first = $results->first();
        $this->assertNotNull($first->token_id);
        $this->assertEquals('john.doe@example.com', $first->original_text);
        $this->assertEquals('email', $first->field);
        $this->assertGreaterThan(0, $first->similarity);
    }

    public function test_complete_with_multiple_contexts(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: ['tenant' => 'company_abc']);
        $this->createAndIndexProduct(1, 'Product X', 'REF-001', cluster: ['tenant' => 'company_xyz']);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_abc',
        ], TestUser::class));
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_xyz',
        ]));

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO('joh=name|pro=name'),
            'limit' => 10,
            'contexts' => $contexts,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
    }

    public function test_complete_returns_empty_when_no_match(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $request = CompletionRequestRecord::from([
            'query' => 'xyz=name',
            'limit' => 10,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertEmpty($results);
    }

    public function test_complete_respects_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createAndIndexUser($i, "User $i", "user$i@example.com");
        }

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO('user=name'),
            'limit' => 3,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertLessThanOrEqual(3, $results->count());
    }

    // ==================== SUGGESTION TESTS ====================

    public function test_suggest_returns_suggestions(): void
    {
        $this->createAndIndexUser(1, 'developer', 'dev@example.com', 'developer');

        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO('devloper=description'),
            'limit' => 10,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->suggest($request);

        $this->assertInstanceOf(SuggestionResultRecordCollection::class, $results);
        $this->assertNotEmpty($results);

        $first = $results->first();
        $this->assertEquals('developer', $first->original_text);
        $this->assertGreaterThanOrEqual(0.3, $first->similarity);
    }

    public function test_suggest_with_multiple_ngrams(): void
    {
        $this->createAndIndexUser(1, 'developer', 'dev@example.com', 'developer');
        $this->createAndIndexUser(2, 'designer', 'design@example.com', 'designer');

        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO('devloper=description|desgner=description'),
            'limit' => 10,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->suggest($request);

        $this->assertNotEmpty($results);
    }

    public function test_suggest_with_fields_filter(): void
    {
        $this->createAndIndexUser(1, 'developer', 'dev@example.com', 'developer');

        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO('devloper=description'),
            'limit' => 10,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->suggest($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertEquals('description', $result->field);
        }
    }

    public function test_suggest_with_namespace_filter(): void
    {
        $this->createAndIndexUser(1, 'developer', 'dev@example.com', 'developer');

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForNamespace(TestUser::class));

        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO('devloper=description'),
            'limit' => 10,
            'contexts' => $contexts,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->suggest($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertEquals('developer', $result->original_text);
        }
    }

    public function test_suggest_returns_empty_when_no_match(): void
    {
        $this->createAndIndexUser(1, 'developer', 'dev@example.com');

        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO('xyz=description'),
            'limit' => 10,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->suggest($request);

        $this->assertEmpty($results);
    }

    public function test_suggest_respects_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createAndIndexUser($i, "developer_$i", "dev$i@example.com");
        }

        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO('devloper=name'),
            'limit' => 3,
            'min_similarity' => 0.1,
        ]);

        $results = $this->hermes->suggest($request);

        $this->assertLessThanOrEqual(3, $results->count());
    }

    // ==================== SEARCH TESTS ====================

    public function test_search_returns_documents(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', 'Software Developer');

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name,email,description'),
            'limit' => 20,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertInstanceOf(SearchResultRecordCollection::class, $results);
        $this->assertNotEmpty($results);

        $first = $results->first();

        $this->assertIsString($first->document_id);
        $this->assertNotEmpty($first->document_id);
        $this->assertStringContainsString('TestUser', $first->fingerprint);
        $this->assertArrayHasKey('name', $first->data->toArray());
        $this->assertArrayHasKey('email', $first->data->toArray());
        $this->assertNotEmpty($first->matches);
        $this->assertGreaterThan(0, $first->similarity);
    }

    public function test_search_with_multiple_ngrams(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', 'Software Developer');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com', 'Senior Developer');

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name|developer=description'),
            'limit' => 20,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
    }

    public function test_search_with_fields_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name,email'),
            'limit' => 20,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            foreach ($result->matches as $match) {
                $this->assertInstanceOf(MatchRecord::class, $match);
                $this->assertContains($match->field, ['name', 'email']);
            }
        }
    }

    public function test_search_with_namespace_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexProduct(1, 'Product X', 'REF-001');

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForNamespace(TestUser::class));

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name'),
            'limit' => 20,
            'contexts' => $contexts,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertStringContainsString('TestUser', $result->fingerprint);
        }
    }

    public function test_search_with_cluster_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: ['tenant' => 'company_abc']);
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com', cluster: ['tenant' => 'company_xyz']);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_abc',
        ]));

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name|jane=name'),
            'limit' => 20,
            'contexts' => $contexts,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertStringContainsString('TestUser|1', $result->fingerprint);
            $this->assertStringNotContainsString('TestUser|2', $result->fingerprint);
        }
    }

    public function test_search_with_multiple_contexts(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: ['tenant' => 'company_abc']);
        $this->createAndIndexProduct(1, 'Product X', 'REF-001', cluster: ['tenant' => 'company_xyz']);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_abc',
        ], TestUser::class));
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_xyz',
        ]));

        IndexWriter::class;
        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name|product=name'),
            'limit' => 20,
            'contexts' => $contexts,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
    }

    public function test_search_returns_document_with_matches_detail(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', 'Software Developer');

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name,email,description'),
            'limit' => 20,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);

        $first = $results->first();
        $this->assertNotEmpty($first->matches);

        foreach ($first->matches as $match) {
            $this->assertInstanceOf(MatchRecord::class, $match);
            $this->assertNotEmpty($match->field);
            $this->assertNotEmpty($match->original_text);
            $this->assertIsFloat($match->similarity);
            $this->assertGreaterThanOrEqual(0, $match->similarity);
            $this->assertLessThanOrEqual(1, $match->similarity);
        }
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('xyz=name'),
            'limit' => 20,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertEmpty($results);
    }

    public function test_search_respects_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createAndIndexUser($i, "User $i", "user$i@example.com");
        }

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('user=name,email'),
            'limit' => 5,
            'min_similarity' => 0.1,
        ]);

        $results = $this->hermes->search($request);

        $this->assertLessThanOrEqual(5, $results->count());
    }

    // ==================== COMPLEX SCENARIOS ====================

    public function test_search_with_complex_query_and_filters(): void
    {
        $this->createAndIndexUser(1, 'John Developer', 'john@example.com', 'Software Engineer');
        $this->createAndIndexUser(2, 'Jane Designer', 'jane@example.com', 'UI/UX Designer');
        $this->createAndIndexProduct(1, 'Laptop Pro', 'REF-001', 'High performance laptop');

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForNamespace(TestUser::class));

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name,email|developer=description'),
            'limit' => 20,
            'contexts' => $contexts,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertStringContainsString('TestUser', $result->fingerprint);
        }
    }

    // ==================== CONTEXT FILTER VO TESTS ====================

    public function test_context_filter_vo_throws_exception_when_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one of namespace or clusterQueries must be provided');

        new ContextFilterVO(null, null);
    }

    public function test_context_filter_vo_has_namespace(): void
    {
        $namespace = TestUser::class;

        $context = new ContextFilterVO($namespace, null);

        $this->assertTrue($context->hasNamespace());
        $this->assertFalse($context->hasClusters());
        $this->assertEquals($namespace, $context->namespace);
    }

    public function test_context_filter_vo_has_clusters(): void
    {
        $clusterQueries = new ClusterQueries([
            'cluster' => 'tenant=company_abc',
        ]);

        $context = new ContextFilterVO(null, $clusterQueries);

        $this->assertFalse($context->hasNamespace());
        $this->assertTrue($context->hasClusters());
        $this->assertEquals($clusterQueries, $context->clusterQueries);
    }

    public function test_context_filter_vo_has_both(): void
    {
        $namespace = TestUser::class;
        $clusterQueries = new ClusterQueries([
            'cluster' => 'tenant=company_abc',
        ]);

        $context = new ContextFilterVO($namespace, $clusterQueries);

        $this->assertTrue($context->hasNamespace());
        $this->assertTrue($context->hasClusters());
        $this->assertEquals($namespace, $context->namespace);
        $this->assertEquals($clusterQueries, $context->clusterQueries);
    }

    public function test_context_filter_vo_get_cluster_query(): void
    {
        $clusterQueries = new ClusterQueries([
            'cluster' => 'tenant=company_abc',
        ]);

        $context = new ContextFilterVO(null, $clusterQueries);

        $this->assertEquals('tenant=company_abc', $context->getClusterQuery());
    }

    public function test_context_filter_vo_get_cluster_query_with_multiple(): void
    {
        $clusterQueries = new ClusterQueries([
            'cluster' => 'tenant=company_abc',
            'metadata' => 'status=active',
        ]);

        $context = new ContextFilterVO(null, $clusterQueries);

        $this->assertEquals('tenant=company_abc & status=active', $context->getClusterQuery());
    }

    public function test_context_filter_vo_get_cluster_column(): void
    {
        $clusterQueries = new ClusterQueries([
            'cluster' => 'tenant=company_abc',
        ]);

        $context = new ContextFilterVO(null, $clusterQueries);

        $this->assertEquals('cluster', $context->getClusterColumn());
    }

    public function test_context_filter_vo_get_value(): void
    {
        $namespace = TestUser::class;
        $clusterQueries = new ClusterQueries([
            'cluster' => 'tenant=company_abc',
        ]);

        $context = new ContextFilterVO($namespace, $clusterQueries);

        $value = $context->getValue();

        $this->assertInstanceOf(StrictAssociative::class, $value);
        $this->assertEquals($namespace, $value['namespace']);
        $this->assertEquals(['cluster' => 'tenant=company_abc'], $value['cluster_queries']->toArray());
    }

    // ==================== NESTED DOT NOTATION TESTS ====================

    public function test_complete_with_deep_dot_notation_cluster(): void
    {
        // ✅ AJOUTER LE CLUSTER COMPLET
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: [
            'tenant' => 'company_abc',
            'settings' => [
                'preferences' => [
                    'theme' => 'dark',
                ],
            ],
        ]);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'settings.preferences.theme=dark',
        ]));

        $request = CompletionRequestRecord::from([
            'query' => 'joh=name',
            'limit' => 10,
            'contexts' => $contexts,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
    }

    public function test_complete_with_dot_notation_and_numeric_operator(): void
    {
        // ✅ AJOUTER LE CLUSTER COMPLET
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: [
            'tenant' => 'company_abc',
            'profile' => [
                'years_experience' => 5,
            ],
        ]);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'profile.years_experience>3',
        ]));

        $request = CompletionRequestRecord::from([
            'query' => 'joh=name',
            'limit' => 10,
            'contexts' => $contexts,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
    }

    public function test_search_with_dot_notation_cluster(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: [
            'tenant' => 'company_abc',
            'profile' => [
                'is_verified' => 'yes',
            ],
        ]);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'profile.is_verified=yes',
        ]));

        $request = SearchRequestRecord::from([
            'query' => 'john=name',
            'limit' => 20,
            'contexts' => $contexts,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
    }

    public function test_complete_with_dot_notation_cluster(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: [
            'tenant' => 'company_abc',
            'profile' => [
                'is_verified' => 'yes',
            ],
        ]);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'profile.is_verified=yes',
        ]));

        $request = CompletionRequestRecord::from([
            'query' => 'joh=name',
            'limit' => 10,
            'contexts' => $contexts,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
    }
    // ==================== DEDUPLICATION TESTS ====================

    public function test_complete_does_not_return_duplicate_original_text(): void
    {
        // Créer plusieurs documents avec le même original_text mais des champs différents
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', 'John');
        $this->createAndIndexUser(2, 'John Smith', 'john.smith@example.com', 'John');

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO('john=name,email,description'),
            'limit' => 20,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);

        // Vérifier qu'il n'y a pas de doublons d'original_text
        $originalTexts = [];
        foreach ($results as $result) {
            $originalTexts[] = $result->original_text;
        }

        $uniqueOriginalTexts = array_unique($originalTexts);
        $this->assertCount(count($originalTexts), $uniqueOriginalTexts, 'Duplicate original_text found in completion results');
    }

    public function test_suggest_does_not_return_duplicate_original_text(): void
    {
        // Créer plusieurs documents avec le même original_text mais des champs différents
        $this->createAndIndexUser(1, 'developer', 'dev@example.com', 'developer');
        $this->createAndIndexUser(2, 'developer', 'dev2@example.com', 'developer');

        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO('devloper=name,email,description'),
            'limit' => 20,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->suggest($request);

        $this->assertNotEmpty($results);

        // Vérifier qu'il n'y a pas de doublons d'original_text
        $originalTexts = [];
        foreach ($results as $result) {
            $originalTexts[] = $result->original_text;
        }

        $uniqueOriginalTexts = array_unique($originalTexts);
        $this->assertCount(count($originalTexts), $uniqueOriginalTexts, 'Duplicate original_text found in suggestion results');
    }

    public function test_search_does_not_return_duplicate_document(): void
    {
        // Créer des documents avec le même original_text mais des emails différents
        $this->createAndIndexUser(1, 'John Doe', 'john1@example.com', 'Software Developer');
        $this->createAndIndexUser(2, 'John Doe', 'john2@example.com', 'Software Developer');

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name,email,description'),
            'limit' => 20,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);

        // Vérifier qu'il n'y a pas de doublons de fingerprint
        $fingerprints = [];
        foreach ($results as $result) {
            $fingerprints[] = $result->fingerprint;
        }

        $uniqueFingerprints = array_unique($fingerprints);
        $this->assertCount(count($fingerprints), $uniqueFingerprints, 'Duplicate fingerprint found in search results');
    }
}
