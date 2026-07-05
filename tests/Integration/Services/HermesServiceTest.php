<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Services;

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
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

final class HermesServiceTest extends IntegrationTestCase
{
    private HermesInterface $hermes;

    private IndexerInterface $indexer;

    private const USER_NAMESPACE = 'AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser';

    private const PRODUCT_NAMESPACE = 'AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestProduct';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hermes = $this->app->make(HermesInterface::class);
        $this->indexer = $this->app->make(IndexerInterface::class);
    }

    private function createAndIndexUser(int $id, string $name, string $email, string $description = '', string $cluster = 'tenant:company_abc'): void
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

    private function createAndIndexProduct(int $id, string $name, string $reference, string $description = '', string $cluster = 'tenant:company_abc'): void
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

    // ==================== TESTS COMPLETION ====================

    public function test_complete_returns_completions(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO('joh=name'),
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
        $contexts->add(new ContextFilterVO(self::USER_NAMESPACE));

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO('joh=name'),
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
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: 'tenant:company_abc');
        $this->createAndIndexUser(2, 'Johnny Cash', 'johnny@example.com', cluster: 'tenant:company_xyz');

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO(null, 'tenant:company_abc'));

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO('joh=name'),
            'limit' => 10,
            'contexts' => $contexts,
        ]);

        $results = $this->hermes->complete($request);

        $this->assertNotEmpty($results);
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results->first()->original_text);
    }

    public function test_complete_with_multiple_contexts(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: 'tenant:company_abc');
        $this->createAndIndexProduct(1, 'Product X', 'REF-001', cluster: 'tenant:company_xyz');

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO(self::USER_NAMESPACE));
        $contexts->add(new ContextFilterVO(null, 'tenant:company_xyz'));

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
            'query' => new SearchQueryVO('xyz=name'),
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

    // ==================== TESTS SUGGESTION ====================

    public function test_suggest_returns_suggestions(): void
    {
        // ✅ Ajouter une description
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
        // ✅ Ajouter des descriptions
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
        // ✅ Ajouter une description
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
        // ✅ Ajouter une description
        $this->createAndIndexUser(1, 'developer', 'dev@example.com', 'developer');

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO(self::USER_NAMESPACE));

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

    // ==================== TESTS SEARCH ====================

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

        // ✅ Vérifier que document_id est un UUID (format string non vide)
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
        $contexts->add(new ContextFilterVO(self::USER_NAMESPACE));

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
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: 'tenant:company_abc');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com', cluster: 'tenant:company_xyz');

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO(null, 'tenant:company_abc'));

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name|jane=name'),
            'limit' => 20,
            'contexts' => $contexts,
            'min_similarity' => 0.3,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            // ✅ Vérifier que le fingerprint correspond à l'utilisateur 1 (cluster company_abc)
            $this->assertStringContainsString('TestUser|1', $result->fingerprint);
            // ✅ Vérifier que ce n'est PAS l'utilisateur 2 (cluster company_xyz)
            $this->assertStringNotContainsString('TestUser|2', $result->fingerprint);
        }
    }

    public function test_search_with_multiple_contexts(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: 'tenant:company_abc');
        $this->createAndIndexProduct(1, 'Product X', 'REF-001', cluster: 'tenant:company_xyz');

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO(self::USER_NAMESPACE));
        $contexts->add(new ContextFilterVO(null, 'tenant:company_xyz'));

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

    // ==================== TESTS COMPLEX SCENARIOS ====================

    public function test_search_with_complex_query_and_filters(): void
    {
        $this->createAndIndexUser(1, 'John Developer', 'john@example.com', 'Software Engineer');
        $this->createAndIndexUser(2, 'Jane Designer', 'jane@example.com', 'UI/UX Designer');
        $this->createAndIndexProduct(1, 'Laptop Pro', 'REF-001', 'High performance laptop');

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO(self::USER_NAMESPACE));

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO('john=name,email|developer=description'),
            'limit' => 20,
            'contexts' => $contexts,
            'min_similarity' => 0.3,
            'use_phonetic' => true,
        ]);

        $results = $this->hermes->search($request);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertStringContainsString('TestUser', $result->fingerprint);
        }
    }

    // ==================== TESTS CONTEXT FILTER VO ====================

    public function test_context_filter_vo_throws_exception_when_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one of namespace or cluster must be provided');

        new ContextFilterVO(null, null);
    }

    public function test_context_filter_vo_has_namespace(): void
    {
        $context = new ContextFilterVO(self::USER_NAMESPACE, null);

        $this->assertTrue($context->hasNamespace());
        $this->assertFalse($context->hasCluster());
        $this->assertEquals(self::USER_NAMESPACE, $context->namespace);
    }

    public function test_context_filter_vo_has_cluster(): void
    {
        $context = new ContextFilterVO(null, 'tenant:company_abc');

        $this->assertFalse($context->hasNamespace());
        $this->assertTrue($context->hasCluster());
        $this->assertEquals('tenant:company_abc', $context->cluster);
    }

    public function test_context_filter_vo_has_both(): void
    {
        $context = new ContextFilterVO(self::USER_NAMESPACE, 'tenant:company_abc');

        $this->assertTrue($context->hasNamespace());
        $this->assertTrue($context->hasCluster());
        $this->assertEquals(self::USER_NAMESPACE, $context->namespace);
        $this->assertEquals('tenant:company_abc', $context->cluster);
    }
}
