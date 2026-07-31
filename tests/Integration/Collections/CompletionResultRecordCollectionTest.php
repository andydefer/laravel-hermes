<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Collections;

use AndyDefer\LaravelHermes\Collections\CompletionResultRecordCollection;
use AndyDefer\LaravelHermes\Records\CompletionResultRecord;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;
use InvalidArgumentException;

final class CompletionResultRecordCollectionTest extends IntegrationTestCase
{
    private CompletionResultRecordCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = new CompletionResultRecordCollection;
    }

    private function createTestRecord(
        string $token = 'john',
        string $originalText = 'John Doe',
        string $field = 'name',
        float $similarity = 0.95,
        ?string $tokenId = 'token_123',
        string $documentId = 'doc_1'
    ): CompletionResultRecord {
        return new CompletionResultRecord(
            token_id: $tokenId,
            document_id: $documentId,
            token: $token,
            original_text: $originalText,
            field: $field,
            similarity: $similarity
        );
    }

    // ============================================================
    // TESTS DE CONSTRUCTION ET AJOUT
    // ============================================================

    public function test_can_add_items_to_collection(): void
    {
        $record1 = $this->createTestRecord();
        $record2 = $this->createTestRecord('jane', 'Jane Smith');

        $this->collection->add($record1);
        $this->collection->add($record2);

        $this->assertCount(2, $this->collection);
        $this->assertSame($record1, $this->collection->first());
        $this->assertSame($record2, $this->collection->last());
    }

    public function test_can_add_multiple_items_at_once(): void
    {
        $record1 = $this->createTestRecord();
        $record2 = $this->createTestRecord('jane', 'Jane Smith');
        $record3 = $this->createTestRecord('prod', 'Laptop Pro');

        $this->collection->add($record1, $record2, $record3);

        $this->assertCount(3, $this->collection);
    }

    public function test_throws_exception_when_adding_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->collection->add('invalid');
    }

    public function test_can_be_created_empty(): void
    {
        $collection = new CompletionResultRecordCollection;

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    // ============================================================
    // TESTS D'EXTRACTION DE DONNÉES
    // ============================================================

    public function test_can_get_tokens(): void
    {
        $this->collection->add(
            $this->createTestRecord('john'),
            $this->createTestRecord('jane'),
            $this->createTestRecord('prod')
        );

        $tokens = $this->collection->getTokens();

        $this->assertCount(3, $tokens);
        $this->assertSame(['john', 'jane', 'prod'], $tokens);
    }

    public function test_can_get_original_texts(): void
    {
        $this->collection->add(
            $this->createTestRecord(originalText: 'John Doe'),
            $this->createTestRecord(originalText: 'Jane Smith'),
            $this->createTestRecord(originalText: 'Laptop Pro')
        );

        $texts = $this->collection->getOriginalTexts();

        $this->assertCount(3, $texts);
        $this->assertSame(['John Doe', 'Jane Smith', 'Laptop Pro'], $texts);
    }

    public function test_can_get_token_ids(): void
    {
        $this->collection->add(
            $this->createTestRecord(tokenId: 'token_1'),
            $this->createTestRecord(tokenId: 'token_2'),
            $this->createTestRecord(tokenId: 'token_3')
        );

        $ids = $this->collection->getTokenIds();

        $this->assertCount(3, $ids);
        $this->assertSame(['token_1', 'token_2', 'token_3'], $ids);
    }

    public function test_can_get_token_ids_with_null_values(): void
    {
        $this->collection->add(
            $this->createTestRecord(tokenId: 'token_1'),
            $this->createTestRecord(tokenId: null),
            $this->createTestRecord(tokenId: 'token_3')
        );

        $ids = $this->collection->getTokenIds();

        $this->assertCount(3, $ids);
        $this->assertSame(['token_1', null, 'token_3'], $ids);
    }

    public function test_can_get_document_ids(): void
    {
        $this->collection->add(
            $this->createTestRecord(documentId: 'doc_1'),
            $this->createTestRecord(documentId: 'doc_1'),
            $this->createTestRecord(documentId: 'doc_2')
        );

        $ids = $this->collection->getDocumentIds();

        $this->assertCount(3, $ids);
        $this->assertSame(['doc_1', 'doc_1', 'doc_2'], $ids);
    }

    public function test_can_get_similarities(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.95),
            $this->createTestRecord(similarity: 0.85),
            $this->createTestRecord(similarity: 0.75)
        );

        $similarities = $this->collection->getSimilarities();

        $this->assertCount(3, $similarities);
        $this->assertSame([0.95, 0.85, 0.75], $similarities);
    }

    public function test_can_get_fields(): void
    {
        $this->collection->add(
            $this->createTestRecord(field: 'name'),
            $this->createTestRecord(field: 'email'),
            $this->createTestRecord(field: 'description')
        );

        $fields = $this->collection->getFields();

        $this->assertCount(3, $fields);
        $this->assertSame(['name', 'email', 'description'], $fields);
    }

    // ============================================================
    // TESTS DE FILTRAGE
    // ============================================================

    public function test_can_filter_by_field(): void
    {
        $this->collection->add(
            $this->createTestRecord(field: 'name'),
            $this->createTestRecord(field: 'email'),
            $this->createTestRecord(field: 'name')
        );

        $filtered = $this->collection->filterByField('name');

        $this->assertCount(2, $filtered);
        $this->assertNotSame($filtered, $this->collection);

        foreach ($filtered as $record) {
            $this->assertEquals('name', $record->field);
        }
    }

    public function test_filter_by_field_returns_empty_when_no_match(): void
    {
        $this->collection->add(
            $this->createTestRecord(field: 'email'),
            $this->createTestRecord(field: 'description')
        );

        $filtered = $this->collection->filterByField('name');

        $this->assertCount(0, $filtered);
        $this->assertTrue($filtered->isEmpty());
    }

    public function test_can_filter_by_min_similarity(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.95),
            $this->createTestRecord(similarity: 0.75),
            $this->createTestRecord(similarity: 0.50),
            $this->createTestRecord(similarity: 0.30)
        );

        $filtered = $this->collection->filterByMinSimilarity(0.6);

        $this->assertCount(2, $filtered);
        $this->assertNotSame($filtered, $this->collection);

        foreach ($filtered as $record) {
            $this->assertGreaterThanOrEqual(0.6, $record->similarity);
        }
    }

    public function test_filter_by_min_similarity_returns_all_when_threshold_zero(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.95),
            $this->createTestRecord(similarity: 0.50)
        );

        $filtered = $this->collection->filterByMinSimilarity(0.0);

        $this->assertCount(2, $filtered);
    }

    public function test_filter_by_min_similarity_returns_empty_when_no_match(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.30),
            $this->createTestRecord(similarity: 0.50)
        );

        $filtered = $this->collection->filterByMinSimilarity(0.8);

        $this->assertCount(0, $filtered);
        $this->assertTrue($filtered->isEmpty());
    }

    public function test_can_chain_filters(): void
    {
        $this->collection->add(
            $this->createTestRecord(field: 'name', similarity: 0.95),
            $this->createTestRecord(field: 'name', similarity: 0.75),
            $this->createTestRecord(field: 'email', similarity: 0.95),
            $this->createTestRecord(field: 'name', similarity: 0.50)
        );

        $filtered = $this->collection
            ->filterByField('name')
            ->filterByMinSimilarity(0.8);

        $this->assertCount(1, $filtered);
        $this->assertEquals('name', $filtered->first()->field);
        $this->assertEquals(0.95, $filtered->first()->similarity);
    }

    // ============================================================
    // TESTS DE MEILLEUR RÉSULTAT
    // ============================================================

    public function test_can_get_best_match(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.75),
            $this->createTestRecord(similarity: 0.95),
            $this->createTestRecord(similarity: 0.85)
        );

        $best = $this->collection->getBestMatch();

        $this->assertNotNull($best);
        $this->assertEquals(0.95, $best->similarity);
    }

    public function test_get_best_match_returns_null_when_empty(): void
    {
        $best = $this->collection->getBestMatch();

        $this->assertNull($best);
    }

    public function test_get_best_match_returns_only_record_when_one_item(): void
    {
        $record = $this->createTestRecord(similarity: 0.80);
        $this->collection->add($record);

        $best = $this->collection->getBestMatch();

        $this->assertSame($record, $best);
    }

    public function test_get_best_match_returns_first_when_equal_similarities(): void
    {
        $record1 = $this->createTestRecord(token: 'john', similarity: 0.95);
        $record2 = $this->createTestRecord(token: 'jane', similarity: 0.95);

        $this->collection->add($record1);
        $this->collection->add($record2);

        $best = $this->collection->getBestMatch();

        $this->assertSame($record1, $best);
    }

    // ============================================================
    // TESTS DE REGROUPEMENT
    // ============================================================

    public function test_can_group_by_document(): void
    {
        $this->collection->add(
            $this->createTestRecord(documentId: 'doc_1'),
            $this->createTestRecord(documentId: 'doc_1'),
            $this->createTestRecord(documentId: 'doc_2'),
            $this->createTestRecord(documentId: 'doc_2'),
            $this->createTestRecord(documentId: 'doc_2')
        );

        $groups = $this->collection->groupByDocument();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('doc_1', $groups);
        $this->assertArrayHasKey('doc_2', $groups);
        $this->assertCount(2, $groups['doc_1']);
        $this->assertCount(3, $groups['doc_2']);
        $this->assertInstanceOf(CompletionResultRecordCollection::class, $groups['doc_1']);
    }

    public function test_can_group_by_field(): void
    {
        $this->collection->add(
            $this->createTestRecord(field: 'name'),
            $this->createTestRecord(field: 'name'),
            $this->createTestRecord(field: 'email'),
            $this->createTestRecord(field: 'email'),
            $this->createTestRecord(field: 'email')
        );

        $groups = $this->collection->groupByField();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('name', $groups);
        $this->assertArrayHasKey('email', $groups);
        $this->assertCount(2, $groups['name']);
        $this->assertCount(3, $groups['email']);
        $this->assertInstanceOf(CompletionResultRecordCollection::class, $groups['name']);
    }

    public function test_group_by_document_returns_empty_when_no_items(): void
    {
        $groups = $this->collection->groupByDocument();

        $this->assertEmpty($groups);
    }

    public function test_group_by_field_returns_empty_when_no_items(): void
    {
        $groups = $this->collection->groupByField();

        $this->assertEmpty($groups);
    }

    // ============================================================
    // TESTS DE COLLECTION VIDE
    // ============================================================

    public function test_get_tokens_returns_empty_array_when_empty(): void
    {
        $tokens = $this->collection->getTokens();

        $this->assertEmpty($tokens);
    }

    public function test_get_original_texts_returns_empty_array_when_empty(): void
    {
        $texts = $this->collection->getOriginalTexts();

        $this->assertEmpty($texts);
    }

    public function test_get_token_ids_returns_empty_array_when_empty(): void
    {
        $ids = $this->collection->getTokenIds();

        $this->assertEmpty($ids);
    }

    public function test_get_document_ids_returns_empty_array_when_empty(): void
    {
        $ids = $this->collection->getDocumentIds();

        $this->assertEmpty($ids);
    }

    public function test_get_similarities_returns_empty_array_when_empty(): void
    {
        $similarities = $this->collection->getSimilarities();

        $this->assertEmpty($similarities);
    }

    public function test_get_fields_returns_empty_array_when_empty(): void
    {
        $fields = $this->collection->getFields();

        $this->assertEmpty($fields);
    }

    // ============================================================
    // TESTS DE PERFORMANCE ET VOLUME
    // ============================================================

    public function test_can_handle_large_collection(): void
    {
        $count = 1000;

        for ($i = 0; $i < $count; $i++) {
            $this->collection->add(
                $this->createTestRecord(
                    token: "token_{$i}",
                    originalText: "Text {$i}",
                    field: $i % 2 === 0 ? 'name' : 'email',
                    similarity: 0.5 + ($i / 2000),
                    tokenId: "token_id_{$i}",
                    documentId: 'doc_'.($i % 5)
                )
            );
        }

        $this->assertCount($count, $this->collection);

        $tokens = $this->collection->getTokens();
        $this->assertCount($count, $tokens);

        $filtered = $this->collection->filterByMinSimilarity(0.7);
        $this->assertGreaterThan(0, $filtered->count());
        $this->assertLessThan($count, $filtered->count());

        $groups = $this->collection->groupByDocument();
        $this->assertCount(5, $groups);

        $best = $this->collection->getBestMatch();
        $this->assertNotNull($best);
        $this->assertGreaterThan(0.9, $best->similarity);
    }

    // ============================================================
    // TESTS D'IMMUTABILITÉ
    // ============================================================

    public function test_filters_do_not_modify_original_collection(): void
    {
        $original = new CompletionResultRecordCollection;
        $original->add($this->createTestRecord());
        $original->add($this->createTestRecord(field: 'email'));

        $filtered = $original->filterByField('name');

        $this->assertCount(2, $original);
        $this->assertCount(1, $filtered);
        $this->assertNotSame($filtered, $original);
    }

    public function test_grouping_does_not_modify_original_collection(): void
    {
        $original = new CompletionResultRecordCollection;
        $original->add($this->createTestRecord(documentId: 'doc_1'));
        $original->add($this->createTestRecord(documentId: 'doc_2'));

        $groups = $original->groupByDocument();

        $this->assertCount(2, $original);
        $this->assertCount(1, $groups['doc_1']);
        $this->assertCount(1, $groups['doc_2']);
        $this->assertNotSame($groups['doc_1'], $original);
    }

    // ============================================================
    // TESTS DE TYPE DE RETOUR
    // ============================================================

    public function test_filter_returns_same_type(): void
    {
        $filtered = $this->collection->filterByMinSimilarity(0.5);

        $this->assertInstanceOf(CompletionResultRecordCollection::class, $filtered);
    }

    public function test_filter_by_field_returns_same_type(): void
    {
        $filtered = $this->collection->filterByField('name');

        $this->assertInstanceOf(CompletionResultRecordCollection::class, $filtered);
    }

    // ============================================================
    // TESTS DE CAS LIMITES
    // ============================================================

    public function test_handles_zero_similarity_correctly(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.0),
            $this->createTestRecord(similarity: 0.5)
        );

        $filtered = $this->collection->filterByMinSimilarity(0.0);

        $this->assertCount(2, $filtered);

        $best = $this->collection->getBestMatch();
        $this->assertEquals(0.5, $best->similarity);
    }

    public function test_handles_maximum_similarity_correctly(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 1.0),
            $this->createTestRecord(similarity: 0.5)
        );

        $filtered = $this->collection->filterByMinSimilarity(1.0);

        $this->assertCount(1, $filtered);
        $this->assertEquals(1.0, $filtered->first()->similarity);

        $best = $this->collection->getBestMatch();
        $this->assertEquals(1.0, $best->similarity);
    }
}
