<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogisticsService;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_no',
        'gatepass_pin',
        'shipper_id',
        'consignee_id',
        'driver_id',
        'shipping_company_id',
        'carrier_id',
        'origin_port_id',
        'destination_port_id',
        'logistics_service',
        'shipping_mode',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'logistics_service' => LogisticsService::class,
            'shipping_mode' => ShippingMode::class,
            'status' => ShipmentStatus::class,
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function consignee(): BelongsTo
    {
        return $this->belongsTo(Consignee::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    /**
     * At most one {@see Vehicle} per shipment when a vehicle row is linked (unique shipment_id when set).
     *
     * @return HasOne<Vehicle, $this>
     */
    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class);
    }

    public function trackings(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShipmentDocument::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
