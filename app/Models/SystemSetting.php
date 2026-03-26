<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Application-wide settings. The logo attribute stores a base64-encoded image,
 * optionally as a full data URL (e.g. data:image/png;base64,...).
 */
final class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_name',
        'logo',
        'address',
        'phone',
        'zipcode',
        'country_id',
        'state_id',
        'city_id',
        'auction_api_key',
        'whatsapp_api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auction_api_key' => 'encrypted',
            'whatsapp_api_key' => 'encrypted',
        ];
    }

    /**
     * Singleton row for application-wide settings.
     */
    public static function current(): self
    {
        $existing = self::query()->first();

        if ($existing instanceof self) {
            return $existing;
        }

        return self::query()->create([]);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
