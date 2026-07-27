<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestAddress extends Model implements Indexable
{
    protected $table = 'test_addresses';

    protected $fillable = [
        'id',
        'user_id',
        'street',
        'city',
        'country',
        'postal_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUser::class, 'user_id');
    }

    public function shouldBeIndexed(): bool
    {
        return (bool) $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        if (! $this->relationLoaded('user')) {
            $this->load('user');
        }

        return StrictAssociative::from([
            'address_street' => $this->street,
            'address_city' => $this->city,
            'address_country' => $this->country,
            'address_postal_code' => $this->postal_code,
            'user_name' => $this->user?->name ?? '',
            'user_email' => $this->user?->email ?? '',
            'full_address' => $this->getFullAddress(),
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'address')
            ->with('status', $this->is_active ? 'active' : 'inactive')
            ->whenNotEmpty('city', $this->city)
            ->whenNotEmpty('country', $this->country);
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'user_id' => $this->user_id,
            'street' => $this->street,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'full_address' => $this->getFullAddress(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function getFullAddress(): string
    {
        return implode(', ', array_filter([
            $this->street,
            $this->city,
            $this->country,
            $this->postal_code,
        ]));
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
