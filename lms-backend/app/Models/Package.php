<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'institution_id',
        'title',
        'description',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'package_module')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Rata-rata rating paket (1-5), dibulatkan 2 desimal.
     */
    public function averageRating(): ?float
    {
        $avg = $this->reviews()->avg('rating');

        return $avg === null ? null : round((float) $avg, 2);
    }
}
