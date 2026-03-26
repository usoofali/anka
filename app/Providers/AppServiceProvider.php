<?php

namespace App\Providers;

use App\Enums\EmailLogStatus;
use App\Models\EmailLog;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerNotificationEmailLogging();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerNotificationEmailLogging(): void
    {
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->channel !== 'mail') {
                return;
            }

            if (! method_exists($event->notification, 'toMail')) {
                return;
            }

            $recipientEmail = $event->notifiable->email ?? null;
            if (! is_string($recipientEmail) || $recipientEmail === '') {
                return;
            }

            $mailMessage = call_user_func([$event->notification, 'toMail'], $event->notifiable);
            if (! $mailMessage instanceof MailMessage) {
                return;
            }

            EmailLog::query()->create([
                'mailable_class' => $event->notification::class,
                'recipient_email' => $recipientEmail,
                'subject' => $mailMessage->subject,
                'body' => implode("\n", array_filter(array_merge(
                    [$mailMessage->greeting],
                    $mailMessage->introLines,
                    $mailMessage->outroLines,
                    [$mailMessage->salutation],
                ))),
                'status' => EmailLogStatus::Sent,
                'meta' => [
                    'channel' => 'mail',
                ],
            ]);
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if ($event->channel !== 'mail') {
                return;
            }

            $recipientEmail = $event->notifiable->email ?? null;
            if (! is_string($recipientEmail) || $recipientEmail === '') {
                return;
            }

            $errorMessage = is_string($event->data['message'] ?? null)
                ? $event->data['message']
                : (is_string($event->data['error'] ?? null) ? $event->data['error'] : null);

            $emailLog = EmailLog::query()->create([
                'mailable_class' => $event->notification::class,
                'recipient_email' => $recipientEmail,
                'subject' => null,
                'body' => null,
                'status' => EmailLogStatus::Failed,
                'meta' => [
                    'channel' => 'mail',
                    'data' => $event->data,
                ],
            ]);

            $emailLog->attempts()->create([
                'exception_message' => $errorMessage,
                'smtp_response' => $errorMessage,
                'attempted_at' => now(),
            ]);
        });
    }
}
