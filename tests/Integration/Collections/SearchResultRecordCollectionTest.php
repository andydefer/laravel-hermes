<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Collections;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\Collections\MatchRecordCollection;
use AndyDefer\LaravelHermes\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelHermes\Records\MatchRecord;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class SearchResultRecordCollectionTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private SearchResultRecordCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = new SearchResultRecordCollection;
    }

    private function createTestRecord(
        string $fingerprint,
        float $similarity = 0.8,
        array $matches = []
    ): SearchResultRecord {
        $matchCollection = new MatchRecordCollection;
        foreach ($matches as $match) {
            $matchCollection->add(new MatchRecord(
                field: $match['field'],
                original_text: $match['original_text'],
                similarity: $match['similarity']
            ));
        }

        return new SearchResultRecord(
            document_id: 'doc-'.uniqid(),
            fingerprint: $fingerprint,
            data: StrictAssociative::from([
                'name' => 'Test',
                'email' => 'test@example.com',
            ]),
            matches: $matchCollection,
            similarity: $similarity
        );
    }

    public function test_can_add_items(): void
    {
        $record1 = $this->createTestRecord('App.Models.User|1');
        $record2 = $this->createTestRecord('App.Models.User|2');

        $this->collection->add($record1, $record2);

        $this->assertCount(2, $this->collection);
        $this->assertSame($record1, $this->collection->first());
        $this->assertSame($record2, $this->collection->last());
    }

    public function test_can_get_document_ids(): void
    {
        $record1 = $this->createTestRecord('App.Models.User|1');
        $record2 = $this->createTestRecord('App.Models.User|2');

        $this->collection->add($record1, $record2);

        $ids = $this->collection->getDocumentIds();

        $this->assertCount(2, $ids);
        $this->assertEquals($record1->document_id, $ids[0]);
        $this->assertEquals($record2->document_id, $ids[1]);
    }

    public function test_can_get_fingerprints(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.User|2')
        );

        $fingerprints = $this->collection->getFingerprints();

        $this->assertCount(2, $fingerprints);
        $this->assertContains('App.Models.User|1', $fingerprints);
        $this->assertContains('App.Models.User|2', $fingerprints);
    }

    public function test_can_get_namespaces(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.User|2'),
            $this->createTestRecord('App.Models.Doctor|3')
        );

        $namespaces = $this->collection->getNamespaces();

        $this->assertCount(2, $namespaces);
        $this->assertContains('App.Models.User', $namespaces);
        $this->assertContains('App.Models.Doctor', $namespaces);
    }

    public function test_can_get_ids_from_fingerprints(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.User|2'),
            $this->createTestRecord('App.Models.Doctor|3')
        );

        $ids = $this->collection->getIds();

        $this->assertCount(3, $ids);
        $this->assertContains('1', $ids);
        $this->assertContains('2', $ids);
        $this->assertContains('3', $ids);
    }

    public function test_can_filter_by_min_similarity(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1', 0.95),
            $this->createTestRecord('App.Models.User|2', 0.75),
            $this->createTestRecord('App.Models.User|3', 0.50)
        );

        $filtered = $this->collection->filterByMinSimilarity(0.7);

        $this->assertCount(2, $filtered);
        $this->assertEquals(0.95, $filtered->first()->similarity);
        $this->assertEquals(0.75, $filtered->last()->similarity);
    }

    public function test_can_filter_by_field(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1', 0.8, [
                ['field' => 'name', 'original_text' => 'John', 'similarity' => 1.0],
            ]),
            $this->createTestRecord('App.Models.User|2', 0.8, [
                ['field' => 'email', 'original_text' => 'john@test.com', 'similarity' => 1.0],
            ])
        );

        $filtered = $this->collection->filterByField('name');

        $this->assertCount(1, $filtered);
        $this->assertEquals('App.Models.User|1', $filtered->first()->fingerprint);
    }

    public function test_can_filter_by_namespace(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.User|2'),
            $this->createTestRecord('App.Models.Doctor|3')
        );

        $filtered = $this->collection->filterByNamespace('App.Models.User');

        $this->assertCount(2, $filtered);
        $this->assertEquals('App.Models.User|1', $filtered->first()->fingerprint);
        $this->assertEquals('App.Models.User|2', $filtered->last()->fingerprint);
    }

    public function test_can_filter_by_multiple_namespaces(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.Doctor|2'),
            $this->createTestRecord('App.Models.Pharmacy|3'),
            $this->createTestRecord('App.Models.Product|4')
        );

        $filtered = $this->collection->filterByNamespaces([
            'App.Models.User',
            'App.Models.Doctor',
        ]);

        $this->assertCount(2, $filtered);
        $this->assertEquals('App.Models.User|1', $filtered->first()->fingerprint);
        $this->assertEquals('App.Models.Doctor|2', $filtered->last()->fingerprint);
    }

    public function test_can_get_data_as_array(): void
    {
        $record = $this->createTestRecord('App.Models.User|1');
        $this->collection->add($record);

        $data = $this->collection->getData();

        $this->assertCount(1, $data);
        $this->assertEquals($record->data->toArray(), $data[0]);
    }

    public function test_can_get_matches_as_array(): void
    {
        $record = $this->createTestRecord('App.Models.User|1', 0.8, [
            ['field' => 'name', 'original_text' => 'John', 'similarity' => 1.0],
            ['field' => 'email', 'original_text' => 'john@test.com', 'similarity' => 0.9],
        ]);

        $this->collection->add($record);

        $matches = $this->collection->getMatches();

        $this->assertCount(1, $matches);
        $this->assertCount(2, $matches[0]);

        // Les matchs sont des objets MatchRecord, pas des tableaux
        $this->assertInstanceOf(MatchRecord::class, $matches[0][0]);
        $this->assertInstanceOf(MatchRecord::class, $matches[0][1]);
        $this->assertEquals('name', $matches[0][0]->field);
        $this->assertEquals('email', $matches[0][1]->field);
        $this->assertEquals('John', $matches[0][0]->original_text);
        $this->assertEquals('john@test.com', $matches[0][1]->original_text);
        $this->assertEquals(1.0, $matches[0][0]->similarity);
        $this->assertEquals(0.9, $matches[0][1]->similarity);
    }

    public function test_can_check_if_belongs_to_namespace(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.Doctor|2')
        );

        $this->assertTrue($this->collection->belongsToNamespace('App.Models.User'));
        $this->assertTrue($this->collection->belongsToNamespace('App.Models.Doctor'));
        $this->assertFalse($this->collection->belongsToNamespace('App.Models.Pharmacy'));
    }

    public function test_can_check_if_belongs_to_any_namespace(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.Doctor|2')
        );

        $this->assertTrue($this->collection->belongsToAnyNamespace([
            'App.Models.User',
            'App.Models.Pharmacy',
        ]));
        $this->assertFalse($this->collection->belongsToAnyNamespace([
            'App.Models.Pharmacy',
            'App.Models.Product',
        ]));
    }

    public function test_can_group_by_namespace(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.User|2'),
            $this->createTestRecord('App.Models.Doctor|3'),
            $this->createTestRecord('App.Models.Doctor|4')
        );

        $groups = $this->collection->groupByNamespace();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('App.Models.User', $groups);
        $this->assertArrayHasKey('App.Models.Doctor', $groups);
        $this->assertCount(2, $groups['App.Models.User']);
        $this->assertCount(2, $groups['App.Models.Doctor']);
        $this->assertInstanceOf(SearchResultRecordCollection::class, $groups['App.Models.User']);
    }

    public function test_can_group_by_namespace_and_get_ids(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1'),
            $this->createTestRecord('App.Models.User|2')
        );

        $groups = $this->collection->groupByNamespace();
        $userGroup = $groups['App.Models.User'];

        $ids = $userGroup->getIds();
        $this->assertCount(2, $ids);
        $this->assertContains('1', $ids);
        $this->assertContains('2', $ids);
    }

    public function test_can_chain_filters(): void
    {
        $this->collection->add(
            $this->createTestRecord('App.Models.User|1', 0.95),
            $this->createTestRecord('App.Models.User|2', 0.75),
            $this->createTestRecord('App.Models.Doctor|3', 0.85)
        );

        $filtered = $this->collection
            ->filterByNamespace('App.Models.User')
            ->filterByMinSimilarity(0.8);

        $this->assertCount(1, $filtered);
        $this->assertEquals('App.Models.User|1', $filtered->first()->fingerprint);
        $this->assertEquals(0.95, $filtered->first()->similarity);
    }
}
