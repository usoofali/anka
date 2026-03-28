<?php

use App\Data\VinLookupResult;
use App\Enums\PrealertStatus;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VinLookupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('public');
});

test('shippers can see only their own prealerts', function () {
    $shipper1 = Shipper::factory()->create();
    $shipper2 = Shipper::factory()->create();

    Prealert::factory()->create(['shipper_id' => $shipper1->id, 'vin' => 'VIN1']);
    Prealert::factory()->create(['shipper_id' => $shipper2->id, 'vin' => 'VIN2']);

    Livewire::actingAs($shipper1->user)
        ->test('pages::prealerts.index')
        ->assertSee('VIN1')
        ->assertDontSee('VIN2');
});

test('staff can see all prealerts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $shipper1 = Shipper::factory()->create();
    $shipper2 = Shipper::factory()->create();

    Prealert::factory()->create(['shipper_id' => $shipper1->id, 'vin' => 'VIN1']);
    Prealert::factory()->create(['shipper_id' => $shipper2->id, 'vin' => 'VIN2']);

    Livewire::actingAs($admin)
        ->test('pages::prealerts.index')
        ->assertSee('VIN1')
        ->assertSee('VIN2');
});

test('shippers can submit a new prealert', function () {
    $shipper = Shipper::factory()->create();
    $consignee = Consignee::factory()->create([
        'shipper_id' => $shipper->id,
        'is_default' => true,
    ]);
    $otherConsignee = Consignee::factory()->create([
        'shipper_id' => $shipper->id,
        'is_default' => false,
    ]);

    $carrier = Carrier::factory()->create();
    $port = Port::factory()->create();
    $file = UploadedFile::fake()->create('receipt.pdf', 500, 'application/pdf');

    Livewire::actingAs($shipper->user)
        ->test('pages::prealerts.create')
        ->assertSet('shipper_id', $shipper->id)
        ->assertSet('consignee_id', $consignee->id)
        ->set('vin', 'WDDGF5HBXDR299293')
        ->set('carrier_id', $carrier->id)
        ->set('destination_port_id', $port->id)
        ->set('auction_receipt', $file)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('prealerts.index'));

    $prealert = Prealert::query()->where('vin', 'WDDGF5HBXDR299293')->first(['*']);
    expect($prealert)->not->toBeNull();
    expect($prealert->shipper_id)->toBe($shipper->id);
    expect($prealert->consignee_id)->toBe($consignee->id);
    expect($prealert->status)->toBe(PrealertStatus::Submitted);

    Storage::disk('public')->assertExists($prealert->auction_receipt);
});

test('vin lookup works during creation', function () {
    $shipper = Shipper::factory()->create();
    $vehicle = Vehicle::factory()->create(['vin' => 'WDDGF5HBXDR299293']);

    $mockService = Mockery::mock(VinLookupService::class);
    $mockService->shouldReceive('lookup')
        ->once()
        ->andReturn(VinLookupResult::vehicleReady($vehicle));
    $this->app->instance(VinLookupService::class, $mockService);

    Livewire::actingAs($shipper->user)
        ->test('pages::prealerts.create')
        ->set('vin', 'WDDGF5HBXDR299293')
        ->assertSet('vehicle.id', $vehicle->id);
});

test('staff can review and approve a prealert', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $prealert = Prealert::factory()->create(['status' => PrealertStatus::Submitted]);

    Livewire::actingAs($admin)
        ->test('pages::prealerts.index')
        ->call('openReviewModal', $prealert->id)
        ->call('approvePrealert')
        ->assertHasNoErrors();

    expect($prealert->fresh()->status)->toBe(PrealertStatus::Approved)
        ->and($prealert->fresh()->reviewed_by)->toBe($admin->id);
});

test('staff can reject a prealert with reason', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $prealert = Prealert::factory()->create(['status' => PrealertStatus::Submitted]);

    Livewire::actingAs($admin)
        ->test('pages::prealerts.index')
        ->call('openReviewModal', $prealert->id)
        ->set('rejectionReason', 'Invalid documentation')
        ->call('rejectPrealert')
        ->assertHasNoErrors();

    expect($prealert->fresh()->status)->toBe(PrealertStatus::Rejected)
        ->and($prealert->fresh()->rejection_reason)->toBe('Invalid documentation');
});
