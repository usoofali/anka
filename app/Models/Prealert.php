<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrealertStatus;
use Database\Factories\PrealertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Prealert extends Model
{
    /** @use HasFactory<PrealertFactory> */
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'vin',
        'gatepass_pin',
        'vehicle_id',
        'carrier_id',
        'destination_port_id',
        'action_receipt',
        'status',
        'submitted_at',
        'reviewed_by',
        'notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrealertStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
