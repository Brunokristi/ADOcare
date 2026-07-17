<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VertexTrainingRunStatusNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $event,
        private readonly array $context = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject('Vertex retraining: ' . $this->event)
            ->line('Udalosť retrénovania: ' . $this->event)
            ->line('Run ID: ' . (string) ($this->context['run_id'] ?? '-'))
            ->line('Dataset ver.: ' . (string) ($this->context['dataset_version'] ?? '-'))
            ->line('Nové príklady: ' . (string) ($this->context['new_examples'] ?? '-'))
            ->line('Tuning job: ' . (string) ($this->context['tuning_job_name'] ?? '-'))
            ->line('Predchádzajúci endpoint: ' . (string) ($this->context['previous_endpoint'] ?? '-'))
            ->line('Kandidát endpoint: ' . (string) ($this->context['candidate_endpoint'] ?? '-'))
            ->line('Stav: ' . (string) ($this->context['status'] ?? '-'))
            ->line('Skóre kandidát/current: ' . (string) ($this->context['candidate_score'] ?? '-') . ' / ' . (string) ($this->context['current_score'] ?? '-'))
            ->line('Fail stage: ' . (string) ($this->context['failure_stage'] ?? '-'))
            ->line('Správa: ' . (string) ($this->context['message'] ?? '-'));

        return $mail;
    }
}
