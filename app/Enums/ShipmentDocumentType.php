<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentDocumentType: string
{
    case BillOfLading = 'bill-of-lading';
    case CommercialInvoice = 'commercial-invoice';
    case CustomsDeclaration = 'customs-declaration';
    case PackingList = 'packing-list';
    case BillOfSale = 'bill-of-sale';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BillOfLading => __('Bill of lading'),
            self::CommercialInvoice => __('Commercial invoice'),
            self::CustomsDeclaration => __('Customs declaration'),
            self::PackingList => __('Packing list'),
            self::BillOfSale => __('Bill of sale'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
