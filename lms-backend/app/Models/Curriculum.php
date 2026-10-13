<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    protected $fillable = [
        'owner_id',
        'institution_id',
        'title',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'institution_id' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }
}
