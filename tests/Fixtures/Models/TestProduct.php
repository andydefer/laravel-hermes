<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Model;

class TestProduct extends Model implements Indexable
{
    protected $table = 'test_products';

    protected $fillable = [
        'id',
        'name',
        'reference',
        'description',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'bool',
    ];

    public function shouldBeIndexed(): bool
    {
        return (bool) $this->is_published;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'reference' => $this->reference,
            'description' => $this->description,
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return new ClusterVO([
            'type' => 'product',
            'status' => 'published',
        ]);
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'name' => $this->name,
            'reference' => $this->reference,
            'description' => $this->description,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ]);
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
