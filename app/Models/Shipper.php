<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShipperFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Shipper extends Model
{
    /** @use HasFactory<ShipperFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'phone',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consignees(): HasMany
    {
        return $this->hasMany(Consignee::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }
}
