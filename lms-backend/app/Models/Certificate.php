<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'enrollment_id',
        'status',
        'certificate_code',
        'notes',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Generate kode sertifikat unik, format: CERT-XXXXXXXX-YYYY
     */
    public static function generateCode(): string
    {
        do {
            $code = 'CERT-'.strtoupper(Str::random(8)).'-'.date('Y');
        } while (static::where('certificate_code', $code)->exists());

        return $code;
    }
}
