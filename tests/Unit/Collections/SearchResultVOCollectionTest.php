<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Unit\Collections;

use AndyDefer\DomainStructures\Collections\Core\DataCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\Collections\MatchRecordCollection;
use AndyDefer\LaravelHermes\Collections\SearchResultVOCollection;
use AndyDefer\LaravelHermes\Records\MatchRecord;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;
use AndyDefer\LaravelHermes\Tests\Fixtures\Datas\TestUserData;
use AndyDefer\LaravelHermes\ValueObjects\SearchResultVO;
use PHPUnit\Framework\TestCase;

final class SearchResultVOCollectionTest extends TestCase
{
    private function createTestRecord(
        string $fingerprint,
        float $similarity = 0.8,
        array $matches = []
    ): SearchResultRecord {
        $matchRecords = [];
        foreach ($matches as $match) {
            $matchRecords[] = new MatchRecord(
                field: $match['field'],
                original_text: $match['original_text'],
                similarity: $match['similarity']
            );
        }

        return new SearchResultRecord(
            document_id: 'doc-'.uniqid(),
            fingerprint: $fingerprint,
            data: StrictAssociative::from([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]),
            matches: MatchRecordCollection::from($matchRecords),
            similarity: $similarity
        );
    }

    private function createTestData(string $fingerprint): TestUserData
    {
        return new TestUserData(
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            fingerprint: $fingerprint
        );
    }

    private function createSearchResultVO(
        string $fingerprint,
        float $similarity = 0.8,
        array $matches = []
    ): SearchResultVO {
        $record = $this->createTestRecord($fingerprint, $similarity, $matches);
        $data = $this->createTestData($fingerprint);

        return new SearchResultVO($record, $data);
    }

    // ============================================================
    // TESTS DE CONSTRUCTION
    // ============================================================

    public function test_can_create_empty_collection(): void
    {
        $collection = new SearchResultVOCollection;

        $this->assertInstanceOf(SearchResultVOCollection::class, $collection);
        $this->assertCount(0, $collection);
        $this->assertTrue($collection->isEmpty());
    }

    public function test_can_add_items(): void
    {
        $collection = new SearchResultVOCollection;
        $vo1 = $this->createSearchResultVO('App.Models.User|1', 0.95);
        $vo2 = $this->createSearchResultVO('App.Models.User|2', 0.75);

        $collection->add($vo1);
        $collection->add($vo2);

        $this->assertCount(2, $collection);
        $this->assertSame($vo1, $collection->first());
        $this->assertSame($vo2, $collection->last());
    }

    // ============================================================
    // TESTS DE getDatas()
    // ============================================================

    public function test_get_datas_returns_data_collection(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO('App.Models.User|1', 0.95));
        $collection->add($this->createSearchResultVO('App.Models.User|2', 0.75));

        $datas = $collection->getDatas();

        $this->assertInstanceOf(DataCollection::class, $datas);
        $this->assertCount(2, $datas);
        $this->assertInstanceOf(TestUserData::class, $datas->first());
    }

    // ============================================================
    // TESTS DE getFingerprints()
    // ============================================================

    public function test_get_fingerprints_returns_typed_collection(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO('App.Models.User|1', 0.95));
        $collection->add($this->createSearchResultVO('App.Models.Doctor|2', 0.75));

        $fingerprints = $collection->getFingerprints();

        $this->assertInstanceOf(StringTypedCollection::class, $fingerprints);
        $this->assertCount(2, $fingerprints);
        $this->assertEquals('App.Models.User|1', $fingerprints->first());
        $this->assertEquals('App.Models.Doctor|2', $fingerprints->last());
    }

    // ============================================================
    // TESTS DE getMatches()
    // ============================================================

    public function test_get_matches_returns_match_record_collection(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO(
            'App.Models.User|1',
            0.95,
            [
                ['field' => 'name', 'original_text' => 'John', 'similarity' => 1.0],
                ['field' => 'email', 'original_text' => 'john@test.com', 'similarity' => 0.9],
            ]
        ));

        $matches = $collection->getMatches();

        $this->assertInstanceOf(MatchRecordCollection::class, $matches);
        $this->assertCount(2, $matches);
        $this->assertEquals('name', $matches->first()->field);
        $this->assertEquals('email', $matches->last()->field);
    }

    // ============================================================
    // TESTS DE filterByMinSimilarity()
    // ============================================================

    public function test_filter_by_min_similarity(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO('App.Models.User|1', 0.95));
        $collection->add($this->createSearchResultVO('App.Models.User|2', 0.75));
        $collection->add($this->createSearchResultVO('App.Models.User|3', 0.50));

        $filtered = $collection->filterByMinSimilarity(0.7);

        $this->assertInstanceOf(SearchResultVOCollection::class, $filtered);
        $this->assertCount(2, $filtered);
        $this->assertEquals(0.95, $filtered->first()->getValue()['similarity']);
        $this->assertEquals(0.75, $filtered->last()->getValue()['similarity']);
    }

    // ============================================================
    // TESTS DE filterByNamespace()
    // ============================================================

    public function test_filter_by_namespace(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO('App.Models.User|1', 0.95));
        $collection->add($this->createSearchResultVO('App.Models.User|2', 0.75));
        $collection->add($this->createSearchResultVO('App.Models.Doctor|3', 0.85));

        $filtered = $collection->filterByNamespace('App.Models.User');

        $this->assertInstanceOf(SearchResultVOCollection::class, $filtered);
        $this->assertCount(2, $filtered);
        $this->assertEquals('App.Models.User|1', $filtered->first()->getValue()['fingerprint']);
        $this->assertEquals('App.Models.User|2', $filtered->last()->getValue()['fingerprint']);
    }

    // ============================================================
    // TESTS DE filterByNamespaces()
    // ============================================================

    public function test_filter_by_multiple_namespaces(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO('App.Models.User|1', 0.95));
        $collection->add($this->createSearchResultVO('App.Models.Doctor|2', 0.85));
        $collection->add($this->createSearchResultVO('App.Models.Pharmacy|3', 0.75));

        $filtered = $collection->filterByNamespaces(['App.Models.User', 'App.Models.Doctor']);

        $this->assertInstanceOf(SearchResultVOCollection::class, $filtered);
        $this->assertCount(2, $filtered);
        $this->assertEquals('App.Models.User|1', $filtered->first()->getValue()['fingerprint']);
        $this->assertEquals('App.Models.Doctor|2', $filtered->last()->getValue()['fingerprint']);
    }

    // ============================================================
    // TESTS DE groupByNamespace()
    // ============================================================

    public function test_group_by_namespace(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO('App.Models.User|1', 0.95));
        $collection->add($this->createSearchResultVO('App.Models.User|2', 0.75));
        $collection->add($this->createSearchResultVO('App.Models.Doctor|3', 0.85));

        $groups = $collection->groupByNamespace();

        $this->assertInstanceOf(StrictAssociative::class, $groups);

        // Convertir en tableau pour les assertions
        $groupsArray = $groups->toArray();

        $this->assertCount(2, $groupsArray);
        $this->assertArrayHasKey('App.Models.User', $groupsArray);
        $this->assertArrayHasKey('App.Models.Doctor', $groupsArray);
        $this->assertCount(2, $groupsArray['App.Models.User']);
        $this->assertCount(1, $groupsArray['App.Models.Doctor']);
        $this->assertInstanceOf(SearchResultVOCollection::class, $groupsArray['App.Models.User']);
    }

    // ============================================================
    // TESTS DE CHAÎNAGE
    // ============================================================

    public function test_can_chain_filters(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO('App.Models.User|1', 0.95));
        $collection->add($this->createSearchResultVO('App.Models.User|2', 0.75));
        $collection->add($this->createSearchResultVO('App.Models.Doctor|3', 0.85));

        $filtered = $collection
            ->filterByNamespace('App.Models.User')
            ->filterByMinSimilarity(0.8);

        $this->assertInstanceOf(SearchResultVOCollection::class, $filtered);
        $this->assertCount(1, $filtered);
        $this->assertEquals('App.Models.User|1', $filtered->first()->getValue()['fingerprint']);
        $this->assertEquals(0.95, $filtered->first()->getValue()['similarity']);
    }

    // ============================================================
    // TESTS DE TO ARRAY
    // ============================================================

    public function test_can_convert_to_array(): void
    {
        $collection = new SearchResultVOCollection;
        $collection->add($this->createSearchResultVO('App.Models.User|1', 0.95));

        $array = $collection->toArray();

        $this->assertIsArray($array);
        $this->assertCount(1, $array);

        // $array[0] est un SearchResultVO, pas un tableau
        $this->assertInstanceOf(SearchResultVO::class, $array[0]);

        // Utiliser getValue() pour obtenir les données
        $value = $array[0]->getValue();
        $this->assertArrayHasKey('data', $value->toArray());
        $this->assertArrayHasKey('similarity', $value->toArray());
        $this->assertArrayHasKey('matches', $value->toArray());
        $this->assertArrayHasKey('fingerprint', $value->toArray());
        $this->assertEquals('App.Models.User|1', $value->toArray()['fingerprint']);
        $this->assertEquals(0.95, $value->toArray()['similarity']);
    }
}
