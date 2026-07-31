<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Collections;

use AndyDefer\LaravelHermes\Collections\MatchRecordCollection;
use AndyDefer\LaravelHermes\Records\MatchRecord;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;
use InvalidArgumentException;

final class MatchRecordCollectionTest extends IntegrationTestCase
{
    private MatchRecordCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = new MatchRecordCollection;
    }

    private function createTestRecord(
        string $field = 'name',
        string $originalText = 'John Doe',
        float $similarity = 0.95
    ): MatchRecord {
        return new MatchRecord(
            field: $field,
            original_text: $originalText,
            similarity: $similarity
        );
    }

    // ============================================================
    // TESTS DE CONSTRUCTION ET AJOUT
    // ============================================================

    public function test_can_add_items_to_collection(): void
    {
        $record1 = $this->createTestRecord();
        $record2 = $this->createTestRecord('email', 'john@test.com');

        $this->collection->add($record1);
        $this->collection->add($record2);

        $this->assertCount(2, $this->collection);
        $this->assertSame($record1, $this->collection->first());
        $this->assertSame($record2, $this->collection->last());
    }

    public function test_can_add_multiple_items_at_once(): void
    {
        $record1 = $this->createTestRecord();
        $record2 = $this->createTestRecord('email', 'john@test.com');
        $record3 = $this->createTestRecord('description', 'Developer');

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
        $collection = new MatchRecordCollection;

        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    // ============================================================
    // TESTS D'EXTRACTION DE DONNÉES
    // ============================================================

    public function test_can_get_fields(): void
    {
        $this->collection->add(
            $this->createTestRecord('name'),
            $this->createTestRecord('email'),
            $this->createTestRecord('description')
        );

        $fields = $this->collection->getFields();

        $this->assertCount(3, $fields);
        $this->assertSame(['name', 'email', 'description'], $fields);
    }

    public function test_can_get_original_texts(): void
    {
        $this->collection->add(
            $this->createTestRecord(originalText: 'John Doe'),
            $this->createTestRecord(originalText: 'john@test.com'),
            $this->createTestRecord(originalText: 'Developer')
        );

        $texts = $this->collection->getOriginalTexts();

        $this->assertCount(3, $texts);
        $this->assertSame(['John Doe', 'john@test.com', 'Developer'], $texts);
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

    // ============================================================
    // TESTS DE FILTRAGE
    // ============================================================

    public function test_can_filter_by_field(): void
    {
        $this->collection->add(
            $this->createTestRecord('name'),
            $this->createTestRecord('email'),
            $this->createTestRecord('name')
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
            $this->createTestRecord('email'),
            $this->createTestRecord('description')
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
            $this->createTestRecord('name', 'John Doe', 0.95),
            $this->createTestRecord('name', 'Jane Smith', 0.75),
            $this->createTestRecord('email', 'john@test.com', 0.95),
            $this->createTestRecord('name', 'Johnny', 0.50)
        );

        $filtered = $this->collection
            ->filterByField('name')
            ->filterByMinSimilarity(0.8);

        $this->assertCount(1, $filtered);
        $this->assertEquals('name', $filtered->first()->field);
        $this->assertEquals(0.95, $filtered->first()->similarity);
    }

    // ============================================================
    // TESTS DE STATISTIQUES
    // ============================================================

    public function test_can_get_average_similarity(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.95),
            $this->createTestRecord(similarity: 0.85),
            $this->createTestRecord(similarity: 0.75)
        );

        $average = $this->collection->getAverageSimilarity();

        $this->assertEquals(0.85, $average);
    }

    public function test_get_average_similarity_returns_zero_when_empty(): void
    {
        $average = $this->collection->getAverageSimilarity();

        $this->assertEquals(0.0, $average);
    }

    public function test_get_average_similarity_with_single_item(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.95)
        );

        $average = $this->collection->getAverageSimilarity();

        $this->assertEquals(0.95, $average);
    }

    public function test_get_average_similarity_with_zero_similarities(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.0),
            $this->createTestRecord(similarity: 0.0)
        );

        $average = $this->collection->getAverageSimilarity();

        $this->assertEquals(0.0, $average);
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
        $record1 = $this->createTestRecord(originalText: 'John', similarity: 0.95);
        $record2 = $this->createTestRecord(originalText: 'Jane', similarity: 0.95);

        $this->collection->add($record1);
        $this->collection->add($record2);

        $best = $this->collection->getBestMatch();

        $this->assertSame($record1, $best);
    }

    // ============================================================
    // TESTS DE REGROUPEMENT
    // ============================================================

    public function test_can_group_by_field(): void
    {
        $this->collection->add(
            $this->createTestRecord('name'),
            $this->createTestRecord('name'),
            $this->createTestRecord('email'),
            $this->createTestRecord('email'),
            $this->createTestRecord('email')
        );

        $groups = $this->collection->groupByField();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('name', $groups);
        $this->assertArrayHasKey('email', $groups);
        $this->assertCount(2, $groups['name']);
        $this->assertCount(3, $groups['email']);
        $this->assertInstanceOf(MatchRecordCollection::class, $groups['name']);
    }

    public function test_group_by_field_returns_empty_when_no_items(): void
    {
        $groups = $this->collection->groupByField();

        $this->assertEmpty($groups);
    }

    public function test_group_by_field_maintains_order_within_groups(): void
    {
        $record1 = $this->createTestRecord('name', 'Alice', 0.95);
        $record2 = $this->createTestRecord('name', 'Bob', 0.85);
        $record3 = $this->createTestRecord('name', 'Charlie', 0.75);

        $this->collection->add($record1, $record2, $record3);

        $groups = $this->collection->groupByField();
        $nameGroup = $groups['name'];

        $this->assertSame($record1, $nameGroup->first());
        $this->assertSame($record3, $nameGroup->last());
    }

    // ============================================================
    // TESTS DE TRI
    // ============================================================

    public function test_can_sort_by_similarity_descending(): void
    {
        $this->collection->add(
            $this->createTestRecord(similarity: 0.75),
            $this->createTestRecord(similarity: 0.95),
            $this->createTestRecord(similarity: 0.85)
        );

        $sorted = $this->collection->sortBySimilarityDesc();

        $this->assertNotSame($sorted, $this->collection);
        $this->assertCount(3, $sorted);

        $this->assertEquals(0.95, $sorted[0]->similarity);
        $this->assertEquals(0.85, $sorted[1]->similarity);
        $this->assertEquals(0.75, $sorted[2]->similarity);
    }

    public function test_sort_by_similarity_desc_with_equal_values(): void
    {
        $record1 = $this->createTestRecord(originalText: 'Alice', similarity: 0.95);
        $record2 = $this->createTestRecord(originalText: 'Bob', similarity: 0.95);
        $record3 = $this->createTestRecord(originalText: 'Charlie', similarity: 0.85);

        $this->collection->add($record1, $record2, $record3);

        $sorted = $this->collection->sortBySimilarityDesc();

        // L'ordre des éléments avec similarité égale n'est pas garanti
        $similarities = $sorted->getSimilarities();
        $this->assertEquals([0.95, 0.95, 0.85], $similarities);
    }

    public function test_sort_by_similarity_desc_returns_empty_when_empty(): void
    {
        $sorted = $this->collection->sortBySimilarityDesc();

        $this->assertCount(0, $sorted);
        $this->assertInstanceOf(MatchRecordCollection::class, $sorted);
    }

    public function test_sort_by_similarity_desc_with_single_item(): void
    {
        $record = $this->createTestRecord(similarity: 0.95);
        $this->collection->add($record);

        $sorted = $this->collection->sortBySimilarityDesc();

        $this->assertCount(1, $sorted);
        $this->assertSame($record, $sorted->first());
    }

    // ============================================================
    // TESTS DE COLLECTION VIDE
    // ============================================================

    public function test_get_fields_returns_empty_array_when_empty(): void
    {
        $fields = $this->collection->getFields();

        $this->assertEmpty($fields);
    }

    public function test_get_original_texts_returns_empty_array_when_empty(): void
    {
        $texts = $this->collection->getOriginalTexts();

        $this->assertEmpty($texts);
    }

    public function test_get_similarities_returns_empty_array_when_empty(): void
    {
        $similarities = $this->collection->getSimilarities();

        $this->assertEmpty($similarities);
    }

    // ============================================================
    // TESTS D'IMMUTABILITÉ
    // ============================================================

    public function test_filters_do_not_modify_original_collection(): void
    {
        $original = new MatchRecordCollection;
        $original->add($this->createTestRecord('name'));
        $original->add($this->createTestRecord('email'));

        $filtered = $original->filterByField('name');

        $this->assertCount(2, $original);
        $this->assertCount(1, $filtered);
        $this->assertNotSame($filtered, $original);
    }

    public function test_sort_does_not_modify_original_collection(): void
    {
        $original = new MatchRecordCollection;
        $original->add($this->createTestRecord(similarity: 0.75));
        $original->add($this->createTestRecord(similarity: 0.95));

        $sorted = $original->sortBySimilarityDesc();

        $this->assertCount(2, $original);
        $this->assertCount(2, $sorted);
        $this->assertNotSame($sorted, $original);
        $this->assertEquals(0.75, $original->first()->similarity);
        $this->assertEquals(0.95, $sorted->first()->similarity);
    }

    // ============================================================
    // TESTS DE TYPE DE RETOUR
    // ============================================================

    public function test_filter_returns_same_type(): void
    {
        $filtered = $this->collection->filterByMinSimilarity(0.5);

        $this->assertInstanceOf(MatchRecordCollection::class, $filtered);
    }

    public function test_filter_by_field_returns_same_type(): void
    {
        $filtered = $this->collection->filterByField('name');

        $this->assertInstanceOf(MatchRecordCollection::class, $filtered);
    }

    public function test_sort_returns_same_type(): void
    {
        $sorted = $this->collection->sortBySimilarityDesc();

        $this->assertInstanceOf(MatchRecordCollection::class, $sorted);
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

    public function test_handles_duplicate_fields_correctly(): void
    {
        $this->collection->add(
            $this->createTestRecord('name', 'John', 0.95),
            $this->createTestRecord('name', 'Johnny', 0.85),
            $this->createTestRecord('name', 'Jon', 0.75)
        );

        $fields = $this->collection->getFields();

        $this->assertCount(3, $fields);
        $this->assertSame(['name', 'name', 'name'], $fields);

        $grouped = $this->collection->groupByField();
        $this->assertCount(1, $grouped);
        $this->assertCount(3, $grouped['name']);
    }

    // ============================================================
    // TESTS DE PERFORMANCE ET VOLUME
    // ============================================================
    public function test_can_handle_large_collection(): void
    {
        $count = 1000;

        for ($i = 0; $i < $count; $i++) {
            $field = $i % 3 === 0 ? 'name' : ($i % 3 === 1 ? 'email' : 'description');
            $similarity = 0.5 + ($i / 2000);

            $this->collection->add(
                $this->createTestRecord(
                    field: $field,
                    originalText: "Text {$i}",
                    similarity: $similarity
                )
            );
        }

        $this->assertCount($count, $this->collection);

        $fields = $this->collection->getFields();
        $this->assertCount($count, $fields);

        $filtered = $this->collection->filterByMinSimilarity(0.7);
        $this->assertGreaterThan(0, $filtered->count());
        $this->assertLessThan($count, $filtered->count());

        $groups = $this->collection->groupByField();
        $this->assertCount(3, $groups);

        $best = $this->collection->getBestMatch();

        $this->assertNotNull($best);
        $this->assertGreaterThanOrEqual(0.9995, $best->similarity);

        $average = $this->collection->getAverageSimilarity();
        $this->assertGreaterThan(0.6, $average);
        $this->assertLessThan(0.85, $average);

        $sorted = $this->collection->sortBySimilarityDesc();

        $this->assertCount($count, $sorted);

    }
}
