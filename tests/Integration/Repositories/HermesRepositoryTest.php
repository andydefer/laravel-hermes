<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\Repositories\HermesRepository;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Support\Collection;

final class HermesRepositoryTest extends IntegrationTestCase
{
    private HermesRepository $repository;

    private IndexerInterface $indexer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(HermesRepository::class);
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

        $tokens = IndexedToken::whereHas('document', function ($q) use ($id) {
            $q->where('fingerprint', 'LIKE', '%|'.$id);
        })->get();
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

    public function test_find_tokens_by_ngrams_returns_tokens(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['joh', 'ohn', 'john'];

        // Act
        $result = $this->repository->findTokensByNgrams($ngrams);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotEmpty($result);

        $tokens = $result->pluck('token')->toArray();
        $this->assertContains('joh', $tokens);
        $this->assertContains('ohn', $tokens);
        $this->assertContains('john', $tokens);
    }

    public function test_find_tokens_by_ngrams_with_limit(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com');
        $ngrams = ['joh', 'joh', 'jan'];

        // Act
        $result = $this->repository->findTokensByNgrams($ngrams, limit: 2);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertLessThanOrEqual(2, $result->count());
    }

    public function test_find_tokens_by_ngrams_with_fields_filter(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $fields = new StringTypedCollection;
        $fields->add('name');

        $ngrams = ['joh', 'ohn', 'john'];

        // Act
        $result = $this->repository->findTokensByNgrams($ngrams, fields: $fields);

        // Assert
        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $this->assertEquals('name', $token->field);
        }
    }

    public function test_find_tokens_by_ngrams_with_namespace_filter(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexProduct(1, 'Product X', 'REF-001');

        $documents = IndexedDocument::all();
        $userNamespace = null;

        foreach ($documents as $doc) {
            $parts = explode('|', $doc->fingerprint);
            $namespace = $parts[0] ?? null;

            if (str_contains($doc->fingerprint, 'TestUser')) {
                $userNamespace = $namespace;
            }
        }

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO($userNamespace));

        $ngrams = ['joh', 'ohn', 'john'];

        // Act
        $result = $this->repository->findTokensByNgrams($ngrams, contexts: $contexts);

        // Assert
        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $this->assertStringContainsString('TestUser', $token->document->fingerprint);
            $this->assertStringNotContainsString('TestProduct', $token->document->fingerprint);
        }
    }

    public function test_find_tokens_by_ngrams_with_cluster_filter(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: 'tenant:company_abc');
        $this->createAndIndexUser(2, 'Jane Doe', 'jane@example.com', cluster: 'tenant:company_xyz');

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO(null, 'tenant:company_abc'));

        $ngrams = ['joh', 'ohn'];

        // Act
        $result = $this->repository->findTokensByNgrams($ngrams, contexts: $contexts);

        // Assert
        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $this->assertStringContainsString('tenant:company_abc', $token->document->cluster);
        }
    }

    public function test_find_tokens_by_ngrams_with_multiple_contexts(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: 'tenant:company_abc');
        $this->createAndIndexProduct(1, 'Product X', 'REF-001', cluster: 'tenant:company_xyz');

        $contexts = new ContextFilterVOCollection;
        $contexts->add(new ContextFilterVO('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser'));
        $contexts->add(new ContextFilterVO(null, 'tenant:company_xyz'));

        $ngrams = ['joh', 'ohn', 'pro', 'rod'];

        // Act
        $result = $this->repository->findTokensByNgrams($ngrams, contexts: $contexts);

        // Assert
        $this->assertNotEmpty($result);

        $hasUser = false;
        $hasProduct = false;

        foreach ($result as $token) {
            if (str_contains($token->document->fingerprint, 'TestUser')) {
                $hasUser = true;
            }
            if (str_contains($token->document->fingerprint, 'TestProduct')) {
                $hasProduct = true;
            }
        }

        $this->assertTrue($hasUser || $hasProduct);
    }

    public function test_find_tokens_by_ngrams_with_document_relation(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['joh', 'ohn'];

        // Act
        $result = $this->repository->findTokensByNgrams($ngrams, withDocument: true);

        // Assert
        $this->assertNotEmpty($result);

        foreach ($result as $token) {
            $this->assertTrue($token->relationLoaded('document'));
            $this->assertNotNull($token->document);
            $this->assertStringContainsString('TestUser', $token->document->fingerprint);
        }
    }

    public function test_find_tokens_by_ngrams_returns_empty_when_no_match(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['xyz', 'abc'];

        // Act
        $result = $this->repository->findTokensByNgrams($ngrams);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    public function test_get_all_tokens_by_ngrams_returns_all_tokens(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Johnny Cash', 'johnny@example.com');
        $ngrams = ['joh'];

        // Act
        $result = $this->repository->getAllTokensByNgrams($ngrams);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->count());
    }

    public function test_get_all_tokens_by_ngrams_with_filters(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com');

        $fields = new StringTypedCollection;
        $fields->add('name');

        $ngrams = ['joh', 'jan'];

        // Act
        $result = $this->repository->getAllTokensByNgrams($ngrams, fields: $fields);

        // Assert
        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $this->assertEquals('name', $token->field);
        }
    }

    public function test_get_tokens_grouped_by_document_returns_grouped_tokens(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['joh', 'ohn', 'john', 'doe'];

        // Act
        $grouped = $this->repository->getTokensGroupedByDocument($ngrams);

        // Assert
        $this->assertIsArray($grouped);
        $this->assertNotEmpty($grouped);

        $firstDoc = reset($grouped);
        $this->assertArrayHasKey('document_id', $firstDoc);
        $this->assertArrayHasKey('fingerprint', $firstDoc);
        $this->assertArrayHasKey('data', $firstDoc);
        $this->assertArrayHasKey('tokens', $firstDoc);
        $this->assertNotEmpty($firstDoc['tokens']);
    }

    public function test_get_tokens_grouped_by_document_groups_by_document_id(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com');
        $ngrams = ['joh', 'ohn', 'jan', 'ane'];

        // Act
        $grouped = $this->repository->getTokensGroupedByDocument($ngrams);

        // Assert
        $this->assertIsArray($grouped);
        $this->assertCount(2, $grouped);

        $fingerprints = array_column($grouped, 'fingerprint');
        $this->assertContains('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', $fingerprints);
        $this->assertContains('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2', $fingerprints);
    }

    public function test_get_tokens_grouped_by_document_with_filters(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexProduct(1, 'Product X', 'REF-001');

        $fields = new StringTypedCollection;
        $fields->add('name');

        $ngrams = ['joh', 'ohn', 'pro', 'rod'];

        // Act
        $grouped = $this->repository->getTokensGroupedByDocument($ngrams, fields: $fields);

        // Assert
        $this->assertNotEmpty($grouped);

        foreach ($grouped as $doc) {
            foreach ($doc['tokens'] as $token) {
                $this->assertEquals('name', $token['field']);
            }
        }
    }

    public function test_get_tokens_grouped_by_document_returns_empty_when_no_match(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['xyz', 'abc'];

        // Act
        $grouped = $this->repository->getTokensGroupedByDocument($ngrams);

        // Assert
        $this->assertIsArray($grouped);
        $this->assertEmpty($grouped);
    }

    public function test_count_tokens_by_ngrams_returns_correct_count(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['joh', 'ohn', 'john'];

        // Act
        $count = $this->repository->countTokensByNgrams($ngrams);

        // Assert
        $this->assertGreaterThan(0, $count);
    }

    public function test_count_tokens_by_ngrams_with_filters(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $fields = new StringTypedCollection;
        $fields->add('name');

        $ngrams = ['joh', 'ohn'];

        // Act
        $count = $this->repository->countTokensByNgrams($ngrams, fields: $fields);

        // Assert
        $this->assertGreaterThan(0, $count);
    }

    public function test_count_tokens_by_ngrams_returns_zero_when_no_match(): void
    {
        // Arrange
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['xyz'];

        // Act
        $count = $this->repository->countTokensByNgrams($ngrams);

        // Assert
        $this->assertEquals(0, $count);
    }
}
