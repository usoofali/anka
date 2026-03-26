<?php

declare(strict_types=1);

namespace App\Enums;

enum PrealertStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingVinLookup = 'pending_vin_lookup';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
