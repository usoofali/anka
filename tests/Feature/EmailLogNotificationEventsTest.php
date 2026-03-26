<?php

declare(strict_types=1);

use App\Enums\EmailLogStatus;
use App\Models\EmailLog;
use App\Models\Shipper;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\ShipperWelcomeNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

it('logs sent mail notification into email_logs', function () {
    SystemSetting::current()->update([
        'company_name' => 'Anka Logistics',
        'phone' => '+123456789',
        'address' => 'HQ Address',
    ]);

    $user = User::factory()->create(['email' => 'shipper@example.com']);
    $shipper = Shipper::factory()->for($user)->create();
    $notification = new ShipperWelcomeNotification($shipper);

    Event::dispatch(new NotificationSent($user, $notification, 'mail'));

    $log = EmailLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->recipient_email)->toBe('shipper@example.com')
        ->and($log->mailable_class)->toBe(ShipperWelcomeNotification::class)
        ->and($log->status)->toBe(EmailLogStatus::Sent);
});

it('logs failed mail notification into email_logs and email_attempts', function () {
    $user = User::factory()->create(['email' => 'missing@example.com']);
    $shipper = Shipper::factory()->for($user)->create();
    $notification = new ShipperWelcomeNotification($shipper);

    Event::dispatch(new NotificationFailed($user, $notification, 'mail', [
        'message' => 'Mailbox not found',
    ]));

    $log = EmailLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->recipient_email)->toBe('missing@example.com')
        ->and($log->status)->toBe(EmailLogStatus::Failed)
        ->and($log->attempts()->count())->toBe(1)
        ->and($log->attempts()->first()?->exception_message)->toBe('Mailbox not found');
});
