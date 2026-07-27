<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestUser extends Model implements Indexable
{
    protected $table = 'test_users';

    protected $fillable = [
        'id',
        'name',
        'email',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
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
            'description' => $this->description,
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'user')
            ->with('status', 'active');
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(TestAddress::class, 'user_id');
    }
}
