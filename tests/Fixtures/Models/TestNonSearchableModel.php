<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;

class TestNonSearchableModel extends Model implements Indexable
{
    protected $table = 'test_non_searchable';

    protected $fillable = [
        'id',
        'name',
    ];

    public function shouldBeIndexed(): bool
    {
        return true;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'non_searchable');
    }

    public function getMorphClass()
    {
        return self::class;
    }

    public function getKey()
    {
        return $this->id;
    }
}
