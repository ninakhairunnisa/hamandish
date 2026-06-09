<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Problem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProblemStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Problem $problem,
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
            'type'       => 'problem_status_changed',
            'problem_id' => $this->problem->id,
            'title'      => $this->problem->title,
            'status'     => $this->problem->status,
            'message'    => "وضعیت مشکل «{$this->problem->title}» به «{$this->problem->status}» تغییر کرد.",
        ];
    }
}
