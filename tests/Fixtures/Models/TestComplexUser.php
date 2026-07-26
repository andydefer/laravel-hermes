<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;

class TestComplexUser extends Model implements Indexable
{
    protected $table = 'test_complex_users';

    protected $fillable = [
        'name',
        'email',
        'metadata',
        'tags',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'metadata' => 'array',
        'tags' => 'array',
    ];

    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'metadata' => $this->metadata,
            'tags' => $this->tags,
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'complex_user')
            ->with('status', $this->is_active ? 'active' : 'inactive')
            ->whenNotEmpty('tags', implode(',', $this->tags ?? []));
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'metadata' => $this->metadata,
            'tags' => $this->tags,
            'is_active' => $this->is_active,
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
