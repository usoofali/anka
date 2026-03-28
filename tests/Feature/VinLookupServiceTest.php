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
        'data' => [
            'id' => 16_100_096,
            'vin' => $vin,
            'year' => 2015,
            'manufacturer' => ['name' => 'TOYOTA'],
            'model' => ['name' => 'COROLLA'],
            'body_type' => ['name' => 'SEDAN'],
            'color' => ['name' => 'CHARCOAL'],
            'engine' => ['name' => '1.8L 4'],
            'transmission' => ['name' => 'AUTOMATIC'],
            'drive_wheel' => ['name' => 'FRONT'],
            'vehicle_type' => ['name' => 'AUTOMOBILE'],
            'fuel' => ['name' => 'GASOLINE'],
            'cylinders' => 4,
            'lots' => [
                [
                    'lot' => '91392225',
                    'keys_available' => true,
                    'odometer' => ['mi' => 105_893],
                    'pre_accident_price' => 15578,
                    'damage' => [
                        'main' => ['name' => 'WATER/FLOOD'],
                        'second' => ['name' => ''],
                    ],
                    'location' => ['raw' => 'FL - WEST PALM BEACH'],
                    'selling_branch' => ['name' => 'COPART_COM'],
                    'title' => ['name' => 'FL - '],
                    'buy_now' => null,
                    'seller' => null,
                    'images' => [
                        'normal' => [
                            'https://cs.copart.com/v1/AUTH_svc.pdoc00001/example_hrs.jpg',
                            'https://cs.copart.com/v1/AUTH_svc.pdoc00001/example_ful.jpg',
                        ],
                    ],
                ],
            ],
        ],
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
    $vehicle = Vehicle::factory()->create([
        'vin' => VinNormalizer::normalize($vin),
    ]);
    $shipment->update(['vehicle_id' => $vehicle->id]);

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
    $vehicle = Vehicle::factory()->create([
        'vin' => VinNormalizer::normalize($vin),
    ]);
    $shipment->update(['vehicle_id' => $vehicle->id]);

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
        'https://api-for-copart-and-iaai.p.rapidapi.com/search-vin/*' => Http::response($payload, 200, ['x-ratelimit-requests-remaining' => 42]),
    ]);

    $result = app(VinLookupService::class)->lookup($vin, $shipper->id);

    expect($result->outcome)->toBe(VinLookupOutcome::FetchedFromApi)
        ->and($result->vehicle)->not->toBeNull()
        ->and($result->vehicle->vin)->toBe(VinNormalizer::normalize($vin))
        ->and($result->vehicle->make)->toBe('TOYOTA')
        ->and($result->vehicle->shipment()->exists())->toBeFalse()
        ->and($result->apiRequestsLeft)->toBe(42)
        ->and($result->vehicle->api_snapshot)->toBeArray()
        ->and($result->vehicle->api_snapshot['car_photo'])->toBeArray()
        ->and($result->vehicle->api_snapshot['car_photo']['photo'])->toHaveCount(2)
        ->and($result->vehicle->copartCarPhotoUrls())->toHaveCount(2)
        ->and($result->vehicle->api_snapshot['sales_history'])->toHaveCount(0)
        ->and($result->vehicle->api_snapshot['currency'])->toBeNull()
        ->and(Cache::get('vin-api:api-request-left'))->toBe(42);
});

it('returns vin not found when api has empty result', function () {
    $shipper = Shipper::factory()->create();
    RateLimiter::clear('vin-api:shipper:'.$shipper->id);
    $vin = '1HGCM82633A004352';

    Http::fake([
        'https://api-for-copart-and-iaai.p.rapidapi.com/search-vin/*' => Http::response([
            'data' => null,
        ], 200, ['x-ratelimit-requests-remaining' => 0]),
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
            'data' => null,
        ], 200, ['x-ratelimit-requests-remaining' => 0]),
    ]);

    $service = app(VinLookupService::class);
    expect($service->lookup('3VW2K7AJ5HM123456', $shipper->id)->outcome)->toBe(VinLookupOutcome::VinNotFound)
        ->and($service->lookup('3VW2K7AJ5HM123457', $shipper->id)->outcome)->toBe(VinLookupOutcome::VinNotFound)
        ->and($service->lookup('3VW2K7AJ5HM123458', $shipper->id)->outcome)->toBe(VinLookupOutcome::RateLimited);
});
