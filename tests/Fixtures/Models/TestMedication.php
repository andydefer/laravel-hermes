<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TestMedication extends Model implements Indexable
{
    protected $table = 'test_medications';

    protected $fillable = [
        'id',
        'name',
        'laboratory',
        'active_substance',
        'dosage',
        'form',
        'description',
        'is_prescription_required',
        'is_active',
    ];

    protected $casts = [
        'is_prescription_required' => 'bool',
        'is_active' => 'bool',
    ];

    public function pharmacies(): BelongsToMany
    {
        return $this->belongsToMany(TestPharmacy::class, 'test_medication_pharmacy', 'medication_id', 'pharmacy_id');
    }

    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'laboratory' => $this->laboratory,
            'active_substance' => $this->active_substance,
            'dosage' => $this->dosage,
            'form' => $this->form,
            'description' => $this->description,
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'medication')
            ->with('status', 'active');
    }

    public function getSearchResultFormat(): StrictAssociative
    {
        return StrictAssociative::from([
            'id' => $this->id,
            'name' => $this->name,
            'laboratory' => $this->laboratory,
            'active_substance' => $this->active_substance,
            'dosage' => $this->dosage,
            'form' => $this->form,
            'description' => $this->description,
            'is_prescription_required' => $this->is_prescription_required,
            'is_active' => $this->is_active,
            'pharmacies' => $this->pharmacies->map(fn ($p) => $p->name)->toArray(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
