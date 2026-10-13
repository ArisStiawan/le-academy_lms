<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'float',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Apakah voucher masih bisa digunakan? */
    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /** Hitung nominal diskon dari harga yang diberikan. */
    public function calculateDiscount(float $price): float
    {
        if ($this->discount_type === 'percent') {
            return round($price * ($this->discount_value / 100), 2);
        }

        // fixed: diskon tidak boleh melebihi harga
        return min($this->discount_value, $price);
    }
}
