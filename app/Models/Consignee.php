<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConsigneeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Consignee extends Model
{
    /** @use HasFactory<ConsigneeFactory> */
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'name',
        'contact',
        'phone',
        'address',
    ];

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
