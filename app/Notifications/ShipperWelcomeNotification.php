<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ShipperWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Shipper $shipper,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Welcome to :app', ['app' => config('app.name')]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->name]))
            ->line(__('Your shipper account for :company is ready.', ['company' => $this->shipper->company_name]))
            ->action(__('Go to dashboard'), url('/dashboard'))
            ->line(__('Thank you for registering with us.'));
    }
}
