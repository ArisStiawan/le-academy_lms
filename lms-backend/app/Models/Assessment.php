<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = [
        'module_id',
        'type',
        'title',
        'max_score',
        'passing_score',
        'xp_reward',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'passing_score' => 'float',
            'xp_reward' => 'integer',
            'due_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('sort_order');
    }
}
