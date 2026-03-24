<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShippingCompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShippingCompany extends Model
{
    /** @use HasFactory<ShippingCompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
