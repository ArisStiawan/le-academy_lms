<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Content extends Model
{
    protected $fillable = [
        'module_id',
        'type',
        'title',
        'file_url',
        'external_url',
        'description',
        'body',
        'sort_order',
        'xp_reward',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'xp_reward' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ContentCompletion::class);
    }

    public function isCompletedBy(User $user): bool
    {
        return $this->completions()
            ->where('user_id', $user->id)
            ->exists();
    }
}
