<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentStatus: string
{
    case Draft = 'draft';
    case Booked = 'booked';
    case AtOrigin = 'at_origin';
    case Inland = 'inland';
    case AtWorkshop = 'at_workshop';
    case InlandAfterWorkshop = 'inland_after_workshop';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
