<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TestPharmacy extends Model implements Indexable
{
    protected $table = 'test_pharmacies';

    protected $fillable = [
        'id',
        'name',
        'address',
        'city',
        'postal_code',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function medications(): BelongsToMany
    {
        return $this->belongsToMany(TestMedication::class, 'test_medication_pharmacy', 'pharmacy_id', 'medication_id');
    }

    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'email' => $this->email,
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'pharmacy')
            ->with('status', 'active');
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'medications_count' => $this->medications->count(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getKey(): int|string
    {
        return $this->id;
    }
}
