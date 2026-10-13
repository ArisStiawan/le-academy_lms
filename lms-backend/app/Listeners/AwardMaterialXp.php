<?php

namespace App\Listeners;

use App\Events\MaterialCompleted;
use App\Services\GamificationService;

class AwardMaterialXp
{
    public function __construct(
        private GamificationService $gamification
    ) {}

    public function handle(MaterialCompleted $event): void
    {
        if ($event->xpReward <= 0) {
            return;
        }

        $this->gamification->awardXp(
            user: $event->user,
            points: $event->xpReward,
            source: 'material_completion',
            referenceId: $event->materialId,
        );
    }
}
