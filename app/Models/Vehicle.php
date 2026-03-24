<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'vin',
        'lot_number',
        'make',
        'model',
        'year',
        'series',
        'body_style',
        'color',
        'vehicle_type',
        'transmission',
        'fuel',
        'engine_type',
        'drive',
        'cylinders',
        'odometer',
        'car_keys',
        'doc_type',
        'primary_damage',
        'secondary_damage',
        'highlights',
        'location',
        'auction_name',
        'seller',
        'est_retail_value',
        'is_insurance',
        'currency_code_id',
        'api_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'is_insurance' => 'boolean',
            'api_snapshot' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
