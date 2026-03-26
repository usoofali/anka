<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

it('returns a single row from current', function () {
    expect(SystemSetting::query()->count())->toBe(0);

    $first = SystemSetting::current();
    $second = SystemSetting::current();

    expect($first->is($second))->toBeTrue();
    expect(SystemSetting::query()->count())->toBe(1);
});

it('persists a long base64 logo string', function () {
    $payload = 'data:image/png;base64,'.base64_encode(str_repeat('x', 500));

    $setting = SystemSetting::factory()->create(['logo' => $payload]);
    $setting->refresh();

    expect($setting->logo)->toBe($payload);
});

it('encrypts auction and whatsapp api keys in the database', function () {
    $setting = SystemSetting::factory()->create([
        'auction_api_key' => 'auction-secret-token',
        'whatsapp_api_key' => 'whatsapp-secret-token',
    ]);

    $setting->refresh();

    expect($setting->auction_api_key)->toBe('auction-secret-token')
        ->and($setting->whatsapp_api_key)->toBe('whatsapp-secret-token');

    $row = DB::table('system_settings')->where('id', $setting->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->auction_api_key)->not->toBe('auction-secret-token')
        ->and($row->whatsapp_api_key)->not->toBe('whatsapp-secret-token');
});

it('persists tracking number configuration defaults and overrides', function () {
    $default = SystemSetting::current()->fresh();

    expect($default)->not->toBeNull()
        ->and($default->tracking_delivery_prefix)->toBe('MRF')
        ->and($default->tracking_digits)->toBe(5)
        ->and($default->tracking_number_type)->toBe('auto_increment')
        ->and($default->tracking_random_digits)->toBe(10);

    $setting = SystemSetting::current();
    $setting->update([
        'tracking_delivery_prefix' => 'TRK',
        'tracking_digits' => 7,
        'tracking_number_type' => 'random',
        'tracking_random_digits' => 12,
    ]);

    expect($setting->fresh()->tracking_delivery_prefix)->toBe('TRK')
        ->and($setting->fresh()->tracking_digits)->toBe(7)
        ->and($setting->fresh()->tracking_number_type)->toBe('random')
        ->and($setting->fresh()->tracking_random_digits)->toBe(12);
});
