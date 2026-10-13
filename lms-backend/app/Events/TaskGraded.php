<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskGraded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public int $submissionId,
        public float $grade,
        public float $threshold = 70
    ) {}
}
