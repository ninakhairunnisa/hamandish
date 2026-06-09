<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Solution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewSolutionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Solution $solution,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'new_solution',
            'problem_id'  => $this->solution->problem_id,
            'solution_id' => $this->solution->id,
            'message'     => 'یک راه‌حل جدید برای مشکل شما ثبت شد.',
        ];
    }
}
