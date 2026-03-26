<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Vehicle;

/**
 * Maps RapidAPI Copart/IAAI `result[]` item into attributes for {@see Vehicle}.
 *
 * @param  array<string, mixed>  $item
 * @param  array<string, mixed>  $envelope  Full API JSON (for snapshot / quota)
 * @return array<string, mixed>
 */
final class MapCopartApiVehicleItemToVehicleAttributes
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    public static function map(array $item, array $envelope = []): array
    {
        $cylinders = $item['cylinders'] ?? null;
        $cylindersInt = is_numeric($cylinders) ? (int) $cylinders : null;

        $year = $item['year'] ?? null;
        $yearString = $year !== null ? (string) $year : null;

        $lotNumber = $item['lot_number'] ?? null;
        $lotString = $lotNumber !== null ? (string) $lotNumber : null;

        $carKeys = $item['car_keys'] ?? null;
        $carKeysString = match (true) {
            $carKeys === true, $carKeys === 1, $carKeys === '1' => '1',
            $carKeys === false, $carKeys === 0, $carKeys === '0' => '0',
            default => null,
        };

        $snapshot = [
            'provider' => 'copart_iaai_rapidapi',
            'api_request_left' => $envelope['api_request_left'] ?? null,
            'provider_vehicle_id' => $item['id'] ?? null,
            'car_photo' => self::normalizeCarPhotoBlock($item['car_photo'] ?? null),
            'sales_history' => is_array($item['sales_history'] ?? null) ? $item['sales_history'] : [],
            'active_bidding' => is_array($item['active_bidding'] ?? null) ? $item['active_bidding'] : [],
            'buy_now_car' => $item['buy_now_car'] ?? null,
            'currency' => is_array($item['currency'] ?? null) ? $item['currency'] : null,
            'result_item' => $item,
        ];

        return [
            'shipment_id' => null,
            'vin' => isset($item['vin']) ? VinNormalizer::normalize((string) $item['vin']) : null,
            'lot_number' => $lotString,
            'make' => self::stringOrNull($item['make'] ?? null),
            'model' => self::stringOrNull($item['model'] ?? null),
            'year' => $yearString,
            'series' => self::stringOrNull($item['series'] ?? null),
            'body_style' => self::stringOrNull($item['body_style'] ?? null),
            'color' => self::stringOrNull($item['color'] ?? null),
            'vehicle_type' => self::stringOrNull($item['vehicle_type'] ?? null),
            'transmission' => self::stringOrNull($item['transmission'] ?? null),
            'fuel' => self::stringOrNull($item['fuel'] ?? null),
            'engine_type' => self::stringOrNull($item['engine_type'] ?? null),
            'drive' => self::stringOrNull($item['drive'] ?? null),
            'cylinders' => $cylindersInt,
            'odometer' => isset($item['odometer']) ? (int) $item['odometer'] : null,
            'car_keys' => $carKeysString,
            'doc_type' => self::stringOrNull($item['doc_type'] ?? null),
            'primary_damage' => self::stringOrNull($item['primary_damage'] ?? null),
            'secondary_damage' => self::stringOrNull($item['secondary_damage'] ?? null),
            'highlights' => self::stringOrNull($item['highlights'] ?? null),
            'location' => self::stringOrNull($item['location'] ?? null),
            'auction_name' => self::stringOrNull($item['auction_name'] ?? null),
            'seller' => self::stringOrNull($item['seller'] ?? null),
            'est_retail_value' => isset($item['est_retail_value'])
                ? number_format((float) $item['est_retail_value'], 2, '.', '')
                : null,
            'is_insurance' => (bool) ($item['is_insurance'] ?? false),
            'currency_code_id' => null,
            'api_snapshot' => $snapshot,
            'api_fetched_at' => now(),
        ];
    }

    /**
     * First-class `car_photo` block for UI (gallery URLs live under `photo`).
     *
     * @return array{id?: int, all_lots_id?: string, photo?: list<string>}|null
     */
    private static function normalizeCarPhotoBlock(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $block = $value;

        if (isset($block['photo']) && is_array($block['photo'])) {
            $urls = [];
            foreach ($block['photo'] as $url) {
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
            $block['photo'] = array_values($urls);
        }

        return $block;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
