<?php

namespace App\Listeners;

use App\Events\TaskGraded;
use App\Models\Submission;
use App\Services\GamificationService;

class AwardTaskXp
{
    public function __construct(
        private GamificationService $gamificationService
    ) {}

    public function handle(TaskGraded $event): void
    {
        $submission = Submission::with('assessment')
            ->find($event->submissionId);

        if (! $submission) {
            return;
        }

        $assessment = $submission->assessment;

        if (! $assessment || $assessment->xp_reward <= 0) {
            return;
        }

        if ($event->grade < $event->threshold) {
            return;
        }

        $this->gamificationService->awardXp(
            user: $event->user,
            points: (int) $assessment->xp_reward,
            source: 'task_completion',
            referenceId: $submission->id,
        );
    }
}
