<?php

declare(strict_types=1);

use App\Domain\Planning\Models\Goal;
use App\Models\User;
use App\Notifications\GoalDeadlineAtRiskNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

it('manda alerta para todos os usuarios com pendencia', function (): void {
    Notification::fake();

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Goal::factory()->create([
        'user_id' => $userA->id,
        'target_amount' => 10000,
        'initial_amount' => 1000,
        'deadline' => CarbonImmutable::now()->addDays(10)->toDateString(),
    ]);

    Goal::factory()->create([
        'user_id' => $userB->id,
        'target_amount' => 10000,
        'initial_amount' => 9500,
        'deadline' => CarbonImmutable::now()->addDays(10)->toDateString(),
    ]);

    $this->artisan('alerts:send-proactive')->assertSuccessful();

    Notification::assertSentTo($userA, GoalDeadlineAtRiskNotification::class);
    Notification::assertNotSentTo($userB, GoalDeadlineAtRiskNotification::class);
});
