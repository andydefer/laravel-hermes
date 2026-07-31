<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Integration\Collections;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\Collections\MatchRecordCollection;
use AndyDefer\LaravelHermes\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelHermes\Records\MatchRecord;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestAddress;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelHermes\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelHermes\Tests\IntegrationTestCase;
use Illuminate\Database\Eloquent\Model;
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

    private function getFingerprintForModel(Model $model): string
    {
        return str_replace('\\', '.', get_class($model)).'|'.$model->id;
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
        $record1 = $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1');
        $record2 = $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2');

        $this->collection->add($record1, $record2);

        $this->assertCount(2, $this->collection);
        $this->assertSame($record1, $this->collection->first());
        $this->assertSame($record2, $this->collection->last());
    }

    public function test_can_get_document_ids(): void
    {
        $record1 = $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1');
        $record2 = $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2');

        $this->collection->add($record1, $record2);

        $ids = $this->collection->getDocumentIds();

        $this->assertCount(2, $ids);
        $this->assertEquals($record1->document_id, $ids[0]);
        $this->assertEquals($record2->document_id, $ids[1]);
    }

    public function test_can_get_fingerprints(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2')
        );

        $fingerprints = $this->collection->getFingerprints();

        $this->assertCount(2, $fingerprints);
        $this->assertContains('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', $fingerprints);
        $this->assertContains('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2', $fingerprints);
    }

    public function test_can_get_namespaces(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|3')
        );

        $namespaces = $this->collection->getNamespaces();

        $this->assertCount(2, $namespaces);
        $this->assertContains('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser', $namespaces);
        $this->assertContains('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor', $namespaces);
    }

    public function test_can_get_ids_from_fingerprints(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|3')
        );

        $ids = $this->collection->getEntityIds();

        $this->assertCount(3, $ids);
        $this->assertContains('1', $ids);
        $this->assertContains('2', $ids);
        $this->assertContains('3', $ids);
    }

    public function test_can_filter_by_min_similarity(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', 0.95),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2', 0.75),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|3', 0.50)
        );

        $filtered = $this->collection->filterByMinSimilarity(0.7);

        $this->assertCount(2, $filtered);
        $this->assertEquals(0.95, $filtered->first()->similarity);
        $this->assertEquals(0.75, $filtered->last()->similarity);
    }

    public function test_can_filter_by_field(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', 0.8, [
                ['field' => 'name', 'original_text' => 'John', 'similarity' => 1.0],
            ]),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2', 0.8, [
                ['field' => 'email', 'original_text' => 'john@test.com', 'similarity' => 1.0],
            ])
        );

        $filtered = $this->collection->filterByField('name');

        $this->assertCount(1, $filtered);
        $this->assertEquals('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', $filtered->first()->fingerprint);
    }

    public function test_can_filter_by_namespace(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|3')
        );

        $filtered = $this->collection->filterByNamespace('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser');

        $this->assertCount(2, $filtered);
        $this->assertEquals('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', $filtered->first()->fingerprint);
        $this->assertEquals('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2', $filtered->last()->fingerprint);
    }

    public function test_can_filter_by_multiple_namespaces(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|2'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestPharmacy|3'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestProduct|4')
        );

        $filtered = $this->collection->filterByNamespaces([
            'AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser',
            'AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor',
        ]);

        $this->assertCount(2, $filtered);
        $this->assertEquals('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', $filtered->first()->fingerprint);
        $this->assertEquals('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|2', $filtered->last()->fingerprint);
    }

    public function test_can_get_data_as_array(): void
    {
        $record = $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1');
        $this->collection->add($record);

        $data = $this->collection->getData();

        $this->assertCount(1, $data);
        $this->assertEquals($record->data->toArray(), $data[0]);
    }

    public function test_can_get_matches_as_array(): void
    {
        $record = $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', 0.8, [
            ['field' => 'name', 'original_text' => 'John', 'similarity' => 1.0],
            ['field' => 'email', 'original_text' => 'john@test.com', 'similarity' => 0.9],
        ]);

        $this->collection->add($record);

        $matches = $this->collection->getMatches();

        $this->assertCount(1, $matches);
        $this->assertCount(2, $matches[0]);

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
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|2')
        );

        $this->assertTrue($this->collection->belongsToNamespace('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser'));
        $this->assertTrue($this->collection->belongsToNamespace('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor'));
        $this->assertFalse($this->collection->belongsToNamespace('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestPharmacy'));
    }

    public function test_can_check_if_belongs_to_any_namespace(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|2')
        );

        $this->assertTrue($this->collection->belongsToAnyNamespace([
            'AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser',
            'AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestPharmacy',
        ]));
        $this->assertFalse($this->collection->belongsToAnyNamespace([
            'AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestPharmacy',
            'AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestProduct',
        ]));
    }

    public function test_can_group_by_namespace(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|3'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|4')
        );

        $groups = $this->collection->groupByNamespace();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser', $groups);
        $this->assertArrayHasKey('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor', $groups);
        $this->assertCount(2, $groups['AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser']);
        $this->assertCount(2, $groups['AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor']);
        $this->assertInstanceOf(SearchResultRecordCollection::class, $groups['AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser']);
    }

    public function test_can_group_by_namespace_and_get_ids(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1'),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2')
        );

        $groups = $this->collection->groupByNamespace();
        $userGroup = $groups['AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser'];

        $ids = $userGroup->getEntityIds();
        $this->assertCount(2, $ids);
        $this->assertContains('1', $ids);
        $this->assertContains('2', $ids);
    }

    public function test_can_chain_filters(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', 0.95),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|2', 0.75),
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestDoctor|3', 0.85)
        );

        $filtered = $this->collection
            ->filterByNamespace('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser')
            ->filterByMinSimilarity(0.8);

        $this->assertCount(1, $filtered);
        $this->assertEquals('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|1', $filtered->first()->fingerprint);
        $this->assertEquals(0.95, $filtered->first()->similarity);
    }

    // ============================================================
    // TESTS DE getModelInstances() AVEC RELATIONS
    // ============================================================

    public function test_get_model_instances_with_relations(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'is_active' => true,
        ]);

        $address = TestAddress::create([
            'addressable_id' => $user->id,
            'addressable_type' => TestUser::class,
            'street' => '123 Main St',
            'city' => 'Paris',
            'country' => 'France',
            'postal_code' => '75001',
            'is_active' => true,
        ]);

        $fingerprint = $this->getFingerprintForModel($user);

        $this->collection->add(
            $this->createTestRecord($fingerprint)
        );

        $instances = $this->collection->getModelInstances(['addresses']);

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(TestUser::class, $instances[0]);
        $this->assertEquals('John Doe', $instances[0]->name);
        $this->assertTrue($instances[0]->relationLoaded('addresses'));
        $this->assertCount(1, $instances[0]->addresses);
        $this->assertEquals('123 Main St', $instances[0]->addresses->first()->street);
    }

    public function test_get_model_instances_with_nested_relations(): void
    {
        $user = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@test.com',
            'is_active' => true,
        ]);

        $address = TestAddress::create([
            'addressable_id' => $user->id,
            'addressable_type' => TestUser::class,
            'street' => '456 Oak Ave',
            'city' => 'Lyon',
            'country' => 'France',
            'postal_code' => '69001',
            'is_active' => true,
        ]);

        $fingerprint = $this->getFingerprintForModel($user);

        $this->collection->add(
            $this->createTestRecord($fingerprint)
        );

        $instances = $this->collection->getModelInstances(['addresses']);

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(TestUser::class, $instances[0]);
        $this->assertEquals('Jane Doe', $instances[0]->name);
        $this->assertTrue($instances[0]->relationLoaded('addresses'));
        $this->assertCount(1, $instances[0]->addresses);
        $this->assertEquals('456 Oak Ave', $instances[0]->addresses->first()->street);
    }

    public function test_get_model_instances_with_relations_on_multiple_classes(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'is_active' => true,
        ]);

        $address = TestAddress::create([
            'addressable_id' => $user->id,
            'addressable_type' => TestUser::class,
            'street' => '123 Main St',
            'city' => 'Paris',
            'country' => 'France',
            'postal_code' => '75001',
            'is_active' => true,
        ]);

        $doctor = TestDoctor::create([
            'first_name' => 'Dr. Jane',
            'last_name' => 'Smith',
            'specialty' => 'Cardiology',
            'email' => 'jane@hospital.com',
            'is_active' => true,
        ]);

        $address2 = TestAddress::create([
            'addressable_id' => $doctor->id,
            'addressable_type' => TestDoctor::class,
            'street' => '456 Oak Ave',
            'city' => 'Lyon',
            'country' => 'France',
            'postal_code' => '69001',
            'is_active' => true,
        ]);

        $fingerprint1 = $this->getFingerprintForModel($user);
        $fingerprint2 = $this->getFingerprintForModel($doctor);

        $this->collection->add(
            $this->createTestRecord($fingerprint1),
            $this->createTestRecord($fingerprint2)
        );

        $instances = $this->collection->getModelInstances(['addresses']);

        $this->assertCount(2, $instances);

        foreach ($instances as $instance) {
            $this->assertTrue($instance->relationLoaded('addresses'));
            $this->assertCount(1, $instance->addresses);
        }
    }

    public function test_get_model_instances_with_empty_relations_does_not_fail(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'is_active' => true,
        ]);

        $fingerprint = $this->getFingerprintForModel($user);

        $this->collection->add(
            $this->createTestRecord($fingerprint)
        );

        $instances = $this->collection->getModelInstances(['addresses']);

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(TestUser::class, $instances[0]);
        $this->assertEquals('John Doe', $instances[0]->name);
        $this->assertTrue($instances[0]->relationLoaded('addresses'));
        $this->assertCount(0, $instances[0]->addresses);
    }

    public function test_get_model_instances_with_relations_returns_empty_when_no_models_found(): void
    {
        $this->collection->add(
            $this->createTestRecord('AndyDefer.LaravelHermes.Tests.Fixtures.Models.TestUser|999')
        );

        $instances = $this->collection->getModelInstances(['addresses']);

        $this->assertCount(0, $instances);
    }

    public function test_get_model_instances_with_relations_maintains_order(): void
    {
        $user1 = TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@test.com',
            'is_active' => true,
        ]);

        $user2 = TestUser::create([
            'name' => 'Bob',
            'email' => 'bob@test.com',
            'is_active' => true,
        ]);

        $user3 = TestUser::create([
            'name' => 'Charlie',
            'email' => 'charlie@test.com',
            'is_active' => true,
        ]);

        $fingerprint1 = $this->getFingerprintForModel($user2);
        $fingerprint2 = $this->getFingerprintForModel($user1);
        $fingerprint3 = $this->getFingerprintForModel($user3);

        $this->collection->add(
            $this->createTestRecord($fingerprint1),
            $this->createTestRecord($fingerprint2),
            $this->createTestRecord($fingerprint3)
        );

        $instances = $this->collection->getModelInstances(['addresses']);

        $this->assertCount(3, $instances);
        $this->assertEquals('Bob', $instances[0]->name);
        $this->assertEquals('Alice', $instances[1]->name);
        $this->assertEquals('Charlie', $instances[2]->name);
    }

    public function test_get_model_instances_with_relations_and_multiple_ids_per_class(): void
    {
        $user1 = TestUser::create([
            'name' => 'Alice',
            'email' => 'alice@test.com',
            'is_active' => true,
        ]);

        $user2 = TestUser::create([
            'name' => 'Bob',
            'email' => 'bob@test.com',
            'is_active' => true,
        ]);

        $fingerprint1 = $this->getFingerprintForModel($user1);
        $fingerprint2 = $this->getFingerprintForModel($user2);

        $this->collection->add(
            $this->createTestRecord($fingerprint1),
            $this->createTestRecord($fingerprint2)
        );

        $instances = $this->collection->getModelInstances(['addresses']);

        $this->assertCount(2, $instances);
        $this->assertInstanceOf(TestUser::class, $instances[0]);
        $this->assertInstanceOf(TestUser::class, $instances[1]);
        $this->assertEquals('Alice', $instances[0]->name);
        $this->assertEquals('Bob', $instances[1]->name);
        $this->assertTrue($instances[0]->relationLoaded('addresses'));
        $this->assertTrue($instances[1]->relationLoaded('addresses'));
    }
}
