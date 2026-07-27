<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TestDoctor extends Model implements Indexable
{
    protected $table = 'test_doctors';

    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'specialty',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'hospital',
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'specialty' => $this->specialty,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'hospital' => $this->hospital,
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'doctor')
            ->with('status', $this->is_active ? 'active' : 'inactive')
            ->whenNotEmpty('specialty', $this->specialty)
            ->whenNotEmpty('city', $this->city);
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name.' '.$this->last_name,
            'specialty' => $this->specialty,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'hospital' => $this->hospital,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(TestAddress::class, 'addressable');
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
