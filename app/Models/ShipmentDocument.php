<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShipmentDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShipmentDocument extends Model
{
    /** @use HasFactory<ShipmentDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'document_type_id',
        'notes',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ShipmentDocumentFile::class);
    }
}
