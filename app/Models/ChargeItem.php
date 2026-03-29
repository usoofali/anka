<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChargeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ChargeItem extends Model
{
    /** @use HasFactory<ChargeItemFactory> */
    use HasFactory;

    protected $fillable = [
        'item',
        'description',
    ];
}
