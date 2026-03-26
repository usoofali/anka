<?php

declare(strict_types=1);

use App\Enums\VinLookupOutcome;
use App\Models\Consignee;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\Vehicle;
use App\Services\VinLookupService;
use App\Support\VinNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    config(['services.copart_iaai.key' => 'test-rapidapi-key']);
    Http::preventStrayRequests();
});

afterEach(function (): void {
    Http::fake();
});

function sampleCopartApiPayload(string $vin): array
{
    return [
        'result' => [[
            'id' => 16_100_096,
            'auction_name' => 'COPART_COM',
            'body_style' => 'SEDAN',
            'car_keys' => true,
            'color' => 'CHARCOAL',
            'cylinders' => '4',
            'doc_type' => 'FL - ',
            'drive' => 'FRONT',
            'engine_type' => '1.8L 4',
            'est_retail_value' => 15_578,
            'fuel' => 'GASOLINE',
            'highlights' => 'RUNS AND DRIVES',
            'location' => 'FL - WEST PALM BEACH',
            'lot_number' => '91392225',
            'make' => 'TOYOTA',
            'model' => 'COROLLA',
            'odometer' => 105_893,
            'primary_damage' => 'WATER/FLOOD',
            'secondary_damage' => '',
            'seller' => null,
            'series' => 'L',
            'transmission' => 'AUTOMATIC',
            'vehicle_type' => 'AUTOMOBILE',
            'vin' => $vin,
            'year' => 2015,
            'is_insurance' => 1,
            'currency_code_id' => 1,
            'car_info' => null,
            'car_photo' => [
                'id' => 216_309,
                'all_lots_id' => '91392225',
                'photo' => [
                    'https://cs.copart.com/v1/AUTH_svc.pdoc00001/example_hrs.jpg',
                    'https://cs.copart.com/v1/AUTH_svc.pdoc00001/example_ful.jpg',
                ],
            ],
            'sales_history' => [
                [
                    'id' => 248_087,
                    'all_lots_id' => '91392225',
                    'lot_number' => 63_852_874,
                    'purchase_price' => 2650,
                    'sale_status' => 'Sold',
                    'sold' => 1,
                    'buyer_id' => 46_111,
                    'buyer_state' => 'NG',
                    'buyer_country' => 'NGA',
                    'sale_date' => 1_706_032_800,
                ],
            ],
            'active_bidding' => [],
            'buy_now_car' => null,
            'currency' => [
                'id' => 1,
                'name' => 'US Dollar',
                'char_code' => 'USD',
                'iso_code' => 840,
                'code_id' => 1,
            ],
        ]],
        'api_request_left' => 42,
    ];
}

it('returns vin invalid for bad format', function () {
    $shipper = Shipper::factory()->create();
    RateLimiter::clear('vin-api:shipper:'.$shipper->id);

    $result = app(VinLookupService::class)->lookup('SHORT', $shipper->id);

    expect($result->outcome)->toBe(VinLookupOutcome::VinInvalid);
});

it('returns already on shipment for the same shipper', function () {
    $shipper = Shipper::factory()->create();
    RateLimiter::clear('vin-api:shipper:'.$shipper->id);
    $vin = '2T1BURHE7FC251274';
    $consignee = Consignee::factory()->for($shipper)->create();
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'consignee_id' => $consignee->id,
    ]);
    Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => VinNormalizer::normalize($vin),
    ]);

    $result = app(VinLookupService::class)->lookup($vin, $shipper->id);

    expect($result->outcome)->toBe(VinLookupOutcome::AlreadyOnShipment)
        ->and($result->belongsToAnotherShipper)->toBeFalse()
        ->and($result->shipment?->is($shipment))->toBeTrue();
});

it('flags another shipper when vin is on an active shipment', function () {
    $owner = Shipper::factory()->create();
    $other = Shipper::factory()->create();
    RateLimiter::clear('vin-api:shipper:'.$other->id);
    $vin = '2T1BURHE7FC251274';
    $consignee = Consignee::factory()->for($owner)->create();
    $shipment = Shipment::factory()->create([
        'shipper_id' => $owner->id,
        'consignee_id' => $consignee->id,
    ]);
    Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => VinNormalizer::normalize($vin),
    ]);

    $result = app(VinLookupService::class)->lookup($vin, $other->id);

    expect($result->outcome)->toBe(VinLookupOutcome::AlreadyOnShipment)
        ->and($result->belongsToAnotherShipper)->toBeTrue();
});

it('returns vehicle ready without calling the api when an orphan vehicle exists', function () {
    $shipper = Shipper::factory()->create();
    RateLimiter::clear('vin-api:shipper:'.$shipper->id);
    $vin = '2T1BURHE7FC251274';
    $vehicle = Vehicle::factory()->withoutShipment()->create([
        'vin' => VinNormalizer::normalize($vin),
    ]);

    Http::fake();

    $result = app(VinLookupService::class)->lookup($vin, $shipper->id);

    expect($result->outcome)->toBe(VinLookupOutcome::VehicleReady)
        ->and($result->vehicle?->is($vehicle))->toBeTrue();
    Http::assertNothingSent();
});

it('fetches from api and persists a vehicle', function () {
    $shipper = Shipper::factory()->create();
    RateLimiter::clear('vin-api:shipper:'.$shipper->id);
    $vin = '2T1BURHE7FC251274';
    $payload = sampleCopartApiPayload($vin);

    Http::fake([
        'https://api-for-copart-and-iaai.p.rapidapi.com/search-vin/*' => Http::response($payload, 200),
    ]);

    $result = app(VinLookupService::class)->lookup($vin, $shipper->id);

    expect($result->outcome)->toBe(VinLookupOutcome::FetchedFromApi)
        ->and($result->vehicle)->not->toBeNull()
        ->and($result->vehicle->vin)->toBe(VinNormalizer::normalize($vin))
        ->and($result->vehicle->make)->toBe('TOYOTA')
        ->and($result->vehicle->shipment_id)->toBeNull()
        ->and($result->apiRequestsLeft)->toBe(42)
        ->and($result->vehicle->api_snapshot)->toBeArray()
        ->and($result->vehicle->api_snapshot['car_photo'])->toBeArray()
        ->and($result->vehicle->api_snapshot['car_photo']['photo'])->toHaveCount(2)
        ->and($result->vehicle->copartCarPhotoUrls())->toHaveCount(2)
        ->and($result->vehicle->api_snapshot['sales_history'])->toHaveCount(1)
        ->and($result->vehicle->api_snapshot['currency']['char_code'] ?? null)->toBe('USD')
        ->and(Cache::get('vin-api:api-request-left'))->toBe(42);
});

it('returns vin not found when api has empty result', function () {
    $shipper = Shipper::factory()->create();
    RateLimiter::clear('vin-api:shipper:'.$shipper->id);
    $vin = '1HGCM82633A004352';

    Http::fake([
        'https://api-for-copart-and-iaai.p.rapidapi.com/search-vin/*' => Http::response([
            'result' => [],
            'api_request_left' => 0,
        ], 200),
    ]);

    $result = app(VinLookupService::class)->lookup($vin, $shipper->id);

    expect($result->outcome)->toBe(VinLookupOutcome::VinNotFound);
});

it('applies rate limiting before outbound api calls', function () {
    config(['services.copart_iaai.rate_limit_per_minute' => 2]);
    $shipper = Shipper::factory()->create();
    RateLimiter::clear('vin-api:shipper:'.$shipper->id);

    Http::fake([
        'https://api-for-copart-and-iaai.p.rapidapi.com/search-vin/*' => Http::response([
            'result' => [],
            'api_request_left' => 0,
        ], 200),
    ]);

    $service = app(VinLookupService::class);
    expect($service->lookup('3VW2K7AJ5HM123456', $shipper->id)->outcome)->toBe(VinLookupOutcome::VinNotFound)
        ->and($service->lookup('3VW2K7AJ5HM123457', $shipper->id)->outcome)->toBe(VinLookupOutcome::VinNotFound)
        ->and($service->lookup('3VW2K7AJ5HM123458', $shipper->id)->outcome)->toBe(VinLookupOutcome::RateLimited);
});
