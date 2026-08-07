<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\Repositories\HermesRepository;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

final class HermesRepositoryTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private HermesRepository $repository;

    private IndexerInterface $indexer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(HermesRepository::class);
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

    public function test_find_tokens_by_ngrams_returns_tokens(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['joh', 'ohn', 'john'];

        $result = $this->repository->findTokensByNgrams($ngrams);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotEmpty($result);

        $tokens = $result->pluck('token')->toArray();
        $this->assertContains('joh', $tokens);
        $this->assertContains('ohn', $tokens);
        $this->assertContains('john', $tokens);
    }

    public function test_find_tokens_by_ngrams_with_limit(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com');
        $ngrams = ['joh', 'jan'];

        $result = $this->repository->findTokensByNgrams($ngrams, limit: 2);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertLessThanOrEqual(2, $result->count());
    }

    public function test_find_tokens_by_ngrams_with_fields_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $fields = new StringTypedCollection;
        $fields->add('name');

        $ngrams = ['joh', 'ohn', 'john'];

        $result = $this->repository->findTokensByNgrams($ngrams, fields: $fields);

        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $this->assertEquals('name', $token->field);
        }
    }

    public function test_find_tokens_by_ngrams_with_namespace_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexProduct(1, 'Product X', 'REF-001');

        $userNamespace = TestUser::class;

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForNamespace($userNamespace));

        $ngrams = ['joh', 'ohn', 'john'];

        $result = $this->repository->findTokensByNgrams($ngrams, contexts: $contexts);

        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $this->assertStringContainsString('TestUser', $token->document->fingerprint->getNamespace());
            $this->assertStringNotContainsString('TestProduct', $token->document->fingerprint->getNamespace());
        }
    }

    public function test_find_tokens_by_ngrams_with_cluster_filter(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: ['tenant' => 'company_abc']);
        $this->createAndIndexUser(2, 'Jane Doe', 'jane@example.com', cluster: ['tenant' => 'company_xyz']);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_abc',
        ]));

        $ngrams = ['joh', 'ohn'];

        $result = $this->repository->findTokensByNgrams($ngrams, contexts: $contexts);

        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $document = $token->document;
            $this->assertEquals('company_abc', $document->cluster->get('tenant'));
        }
    }

    public function test_find_tokens_by_ngrams_with_cluster_filter_and_condition(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com', cluster: ['tenant' => 'company_abc', 'status' => 'active']);
        $this->createAndIndexUser(2, 'Jane Doe', 'jane@example.com', cluster: ['tenant' => 'company_abc', 'status' => 'inactive']);

        $contexts = new ContextFilterVOCollection;
        $contexts->add($this->createContextFilterForCluster([
            'cluster' => 'tenant=company_abc & status=active',
        ]));

        $ngrams = ['joh', 'ohn'];

        $result = $this->repository->findTokensByNgrams($ngrams, contexts: $contexts);

        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $document = $token->document;
            $this->assertEquals('company_abc', $document->cluster->get('tenant'));
            $this->assertEquals('active', $document->cluster->get('status'));
        }
    }

    public function test_find_tokens_by_ngrams_with_multiple_contexts(): void
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

        $ngrams = ['joh', 'ohn', 'pro', 'rod'];

        $result = $this->repository->findTokensByNgrams($ngrams, contexts: $contexts);

        $this->assertNotEmpty($result);

        $hasUser = false;
        $hasProduct = false;

        foreach ($result as $token) {
            $namespace = $token->document->fingerprint->getNamespace();
            if (str_contains($namespace, 'TestUser')) {
                $hasUser = true;
            }
            if (str_contains($namespace, 'TestProduct')) {
                $hasProduct = true;
            }
        }

        $this->assertTrue($hasUser || $hasProduct);
    }

    public function test_find_tokens_by_ngrams_with_document_relation(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['joh', 'ohn'];

        $result = $this->repository->findTokensByNgrams($ngrams, withDocument: true);

        $this->assertNotEmpty($result);

        foreach ($result as $token) {
            $this->assertTrue($token->relationLoaded('document'));
            $this->assertNotNull($token->document);
            $this->assertStringContainsString('TestUser', $token->document->fingerprint->getNamespace());
        }
    }

    public function test_find_tokens_by_ngrams_returns_empty_when_no_match(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['xyz', 'abc'];

        $result = $this->repository->findTokensByNgrams($ngrams);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    public function test_get_all_tokens_by_ngrams_returns_all_tokens(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Johnny Cash', 'johnny@example.com');
        $ngrams = ['joh'];

        $result = $this->repository->getAllTokensByNgrams($ngrams);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->count());
    }

    public function test_get_all_tokens_by_ngrams_with_filters(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com');

        $fields = new StringTypedCollection;
        $fields->add('name');

        $ngrams = ['joh', 'jan'];

        $result = $this->repository->getAllTokensByNgrams($ngrams, fields: $fields);

        $this->assertNotEmpty($result);
        foreach ($result as $token) {
            $this->assertEquals('name', $token->field);
        }
    }

    public function test_get_tokens_grouped_by_document_returns_grouped_tokens(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['joh', 'ohn', 'john', 'doe'];

        $grouped = $this->repository->getTokensGroupedByDocument($ngrams);

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
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexUser(2, 'Jane Smith', 'jane@example.com');
        $ngrams = ['joh', 'ohn', 'jan', 'ane'];

        $grouped = $this->repository->getTokensGroupedByDocument($ngrams);

        $this->assertIsArray($grouped);
        $this->assertCount(2, $grouped);

        $fingerprints = action_normalizer_chain()->normalize(array_column($grouped, 'fingerprint'));

        $fingerprint1 = IndexableFingerprintVO::fromParts(TestUser::class, '1')->getValue();
        $fingerprint2 = IndexableFingerprintVO::fromParts(TestUser::class, '2')->getValue();

        $this->assertContains($fingerprint1, $fingerprints);
        $this->assertContains($fingerprint2, $fingerprints);
    }

    public function test_get_tokens_grouped_by_document_with_filters(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $this->createAndIndexProduct(1, 'Product X', 'REF-001');

        $fields = new StringTypedCollection;
        $fields->add('name');

        $ngrams = ['joh', 'ohn', 'pro', 'rod'];

        $grouped = $this->repository->getTokensGroupedByDocument($ngrams, fields: $fields);

        $this->assertNotEmpty($grouped);

        foreach ($grouped as $doc) {
            foreach ($doc['tokens'] as $token) {
                $this->assertEquals('name', $token['field']);
            }
        }
    }

    public function test_get_tokens_grouped_by_document_returns_empty_when_no_match(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['xyz', 'abc'];

        $grouped = $this->repository->getTokensGroupedByDocument($ngrams);

        $this->assertIsArray($grouped);
        $this->assertEmpty($grouped);
    }

    public function test_count_tokens_by_ngrams_returns_correct_count(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['joh', 'ohn', 'john'];

        $count = $this->repository->countTokensByNgrams($ngrams);

        $this->assertGreaterThan(0, $count);
    }

    public function test_count_tokens_by_ngrams_with_filters(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');

        $fields = new StringTypedCollection;
        $fields->add('name');

        $ngrams = ['joh', 'ohn'];

        $count = $this->repository->countTokensByNgrams($ngrams, fields: $fields);

        $this->assertGreaterThan(0, $count);
    }

    public function test_count_tokens_by_ngrams_returns_zero_when_no_match(): void
    {
        $this->createAndIndexUser(1, 'John Doe', 'john@example.com');
        $ngrams = ['xyz'];

        $count = $this->repository->countTokensByNgrams($ngrams);

        $this->assertEquals(0, $count);
    }
}
