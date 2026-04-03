<?php

declare(strict_types=1);

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Enums\VehicleIs;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\ShipmentDocumentFile;
use App\Models\ShipmentTracking;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Notifications\ShipmentDocumentAttachedNotification;
use App\Support\ShipmentTrackingPresenter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

it('allows downloading a shipment document file when the user can view the shipment', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $shipment = Shipment::factory()->create();
    $relativePath = 'shipment-documents/'.$shipment->id.'/doc.pdf';
    Storage::disk('local')->put($relativePath, 'pdf-bytes');

    $document = ShipmentDocument::factory()->create([
        'shipment_id' => $shipment->id,
        'document_type' => ShipmentDocumentType::Other,
    ]);
    $file = ShipmentDocumentFile::factory()->create([
        'shipment_document_id' => $document->id,
        'path' => $relativePath,
        'original_name' => 'invoice.pdf',
    ]);

    actingAs($user);

    $this->get(route('shipments.documents.files.download', [$shipment, $file]))
        ->assertSuccessful();
});

it('returns forbidden when the user cannot view shipments', function (): void {
    $user = User::factory()->create();

    $shipment = Shipment::factory()->create();
    $relativePath = 'shipment-documents/'.$shipment->id.'/doc.pdf';
    Storage::disk('local')->put($relativePath, 'x');

    $document = ShipmentDocument::factory()->create(['shipment_id' => $shipment->id]);
    $file = ShipmentDocumentFile::factory()->create([
        'shipment_document_id' => $document->id,
        'path' => $relativePath,
    ]);

    actingAs($user);

    $this->get(route('shipments.documents.files.download', [$shipment, $file]))
        ->assertForbidden();
});

it('returns not found when the file belongs to another shipment', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $shipmentA = Shipment::factory()->create();
    $shipmentB = Shipment::factory()->create();
    $relativePath = 'shipment-documents/'.$shipmentB->id.'/other.pdf';
    Storage::disk('local')->put($relativePath, 'x');

    $document = ShipmentDocument::factory()->create(['shipment_id' => $shipmentB->id]);
    $file = ShipmentDocumentFile::factory()->create([
        'shipment_document_id' => $document->id,
        'path' => $relativePath,
    ]);

    actingAs($user);

    $this->get(route('shipments.documents.files.download', [$shipmentA, $file]))
        ->assertNotFound();
});

it('stores attached documents and notifies shipper and staff', function (): void {
    Notification::fake();

    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $shipperOwner = User::factory()->create();
    $shipperOwner->assignRole('shipper');

    $shipment = Shipment::factory()->create();
    $shipment->shipper->update(['user_id' => $shipperOwner->id]);

    actingAs($staffUser);

    $pdf = UploadedFile::fake()->create('bol.pdf', 120);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('attachDocumentType', ShipmentDocumentType::BillOfLading->value)
        ->set('attachFiles', [$pdf])
        ->call('saveAttachedDocuments')
        ->assertHasNoErrors();

    expect(ShipmentDocument::query()->where('shipment_id', $shipment->id)->count())->toBe(1);
    expect(ShipmentDocumentFile::query()->count())->toBe(1);

    $this->assertDatabaseHas('activity_logs', [
        'shipment_id' => $shipment->id,
        'user_id' => $staffUser->id,
        'action' => 'document_attached',
    ]);

    $this->assertDatabaseHas('shipment_trackings', [
        'shipment_id' => $shipment->id,
    ]);

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::CargoLoaded);

    Notification::assertSentTo($shipperOwner, ShipmentDocumentAttachedNotification::class, function (ShipmentDocumentAttachedNotification $notification, array $channels) use ($shipperOwner): bool {
        if (! in_array('mail', $channels, true) || ! in_array('database', $channels, true)) {
            return false;
        }
        $payload = $notification->toArray($shipperOwner);
        expect($payload['download_urls'])->toBeArray()->not->toBeEmpty()
            ->and($payload['download_urls'][0]['url'] ?? '')->toContain('signature=');
        expect($payload['title'])->toBe(ShipmentDocumentAttachedNotification::documentAttachedTitle(ShipmentDocumentType::BillOfLading->label()));

        return true;
    });

    Notification::assertSentTo($staffUser, ShipmentDocumentAttachedNotification::class, function ($notification, array $channels): bool {
        return $channels === ['database'];
    });
});

it('does not change shipment status when attaching a non-mapping document type', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Pending,
    ]);

    actingAs($user);

    $file = UploadedFile::fake()->create('extra.pdf', 50);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('attachDocumentType', ShipmentDocumentType::Other->value)
        ->set('attachFiles', [$file])
        ->call('saveAttachedDocuments')
        ->assertHasNoErrors();

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::Pending);
});

it('requires vehicle condition for title documents', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $vehicle = Vehicle::factory()->create();
    $shipment = Shipment::factory()->create(['vehicle_id' => $vehicle->id]);

    actingAs($user);

    $file = UploadedFile::fake()->create('title.pdf', 80);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('attachDocumentType', ShipmentDocumentType::TitleDocument->value)
        ->set('attachTitleVehicleIs', '')
        ->set('attachFiles', [$file])
        ->call('saveAttachedDocuments')
        ->assertHasErrors(['attachTitleVehicleIs']);
});

it('rejects title documents when the shipment has no vehicle', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $shipment = Shipment::factory()->create(['vehicle_id' => null]);

    actingAs($user);

    $file = UploadedFile::fake()->create('title.pdf', 80);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('attachDocumentType', ShipmentDocumentType::TitleDocument->value)
        ->set('attachTitleVehicleIs', VehicleIs::Runner->value)
        ->set('attachFiles', [$file])
        ->call('saveAttachedDocuments')
        ->assertHasErrors(['attachDocumentType']);
});

it('updates vehicle_is when attaching a title document', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $vehicle = Vehicle::factory()->create(['vehicle_is' => null]);
    $shipment = Shipment::factory()->create(['vehicle_id' => $vehicle->id]);

    actingAs($user);

    $file = UploadedFile::fake()->create('title.pdf', 90);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('attachDocumentType', ShipmentDocumentType::TitleDocument->value)
        ->set('attachTitleVehicleIs', VehicleIs::Forklift->value)
        ->set('attachFiles', [$file])
        ->call('saveAttachedDocuments')
        ->assertHasNoErrors();

    $vehicle->refresh();
    expect($vehicle->vehicle_is)->toBe(VehicleIs::Forklift);

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::Inland);
});

it('clears workshop snapshot when a document moves status away from at workshop', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $workshop = Workshop::factory()->create();
    $vehicle = Vehicle::factory()->create();

    $shipment = Shipment::factory()->create([
        'vehicle_id' => $vehicle->id,
        'shipment_status' => ShipmentStatus::AtWorkshop,
        'workshop_id' => $workshop->id,
        'shipment_status_before_workshop' => ShipmentStatus::Inland,
    ]);

    actingAs($user);

    $file = UploadedFile::fake()->create('title.pdf', 90);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('attachDocumentType', ShipmentDocumentType::TitleDocument->value)
        ->set('attachTitleVehicleIs', VehicleIs::Runner->value)
        ->set('attachFiles', [$file])
        ->call('saveAttachedDocuments')
        ->assertHasNoErrors();

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::Inland);
    expect($shipment->workshop_id)->toBeNull();
    expect($shipment->shipment_status_before_workshop)->toBeNull();
});

it('allows staff to remove a shipment document and deletes storage', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $user->id]);

    $shipment = Shipment::factory()->create();
    $relativePath = 'shipment-documents/'.$shipment->id.'/gone.pdf';
    Storage::disk('local')->put($relativePath, 'content');

    $document = ShipmentDocument::factory()->create([
        'shipment_id' => $shipment->id,
        'document_type' => ShipmentDocumentType::Other,
    ]);
    ShipmentDocumentFile::factory()->create([
        'shipment_document_id' => $document->id,
        'path' => $relativePath,
    ]);

    actingAs($user);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('pendingDeleteShipmentDocumentId', $document->id)
        ->call('deleteShipmentDocumentConfirmed')
        ->assertHasNoErrors();

    expect(ShipmentDocument::query()->whereKey($document->id)->exists())->toBeFalse();
    expect(Storage::disk('local')->exists($relativePath))->toBeFalse();

    $this->assertDatabaseHas('activity_logs', [
        'shipment_id' => $shipment->id,
        'action' => 'document_removed',
    ]);
});

it('forbids shippers from deleting documents even when they can attach', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');

    $shipment = Shipment::factory()->create();
    $document = ShipmentDocument::factory()->create([
        'shipment_id' => $shipment->id,
    ]);

    actingAs($user);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->call('openDeleteDocumentConfirm', $document->id)
        ->assertForbidden();
});

it('sends a shipment to workshop and restores status when returned', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $user->id]);

    $workshop = Workshop::factory()->create();
    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::DeliveredToPort,
    ]);

    actingAs($user);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('toWorkshopWorkshopId', $workshop->id)
        ->call('saveToWorkshop')
        ->assertHasNoErrors();

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::AtWorkshop);
    expect($shipment->workshop_id)->toBe($workshop->id);
    expect($shipment->shipment_status_before_workshop)->toBe(ShipmentStatus::DeliveredToPort);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->call('fromWorkshop')
        ->assertHasNoErrors();

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::DeliveredToPort);
    expect($shipment->workshop_id)->toBeNull();
    expect($shipment->shipment_status_before_workshop)->toBeNull();
});

it('forbids shippers from workshop actions', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');

    $workshop = Workshop::factory()->create();
    $shipment = Shipment::factory()->create();

    actingAs($user);

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('toWorkshopWorkshopId', $workshop->id)
        ->call('saveToWorkshop')
        ->assertForbidden();
});

it('allows guest download with a valid signed url', function (): void {
    $shipment = Shipment::factory()->create();
    $relativePath = 'shipment-documents/'.$shipment->id.'/guest.pdf';
    Storage::disk('local')->put($relativePath, 'secret');

    $document = ShipmentDocument::factory()->create(['shipment_id' => $shipment->id]);
    $file = ShipmentDocumentFile::factory()->create([
        'shipment_document_id' => $document->id,
        'path' => $relativePath,
    ]);

    $url = URL::temporarySignedRoute(
        'shipments.documents.files.download.signed',
        now()->addHour(),
        ['shipment' => $shipment->id, 'file' => $file->id],
    );

    $this->get($url)->assertSuccessful();
});

it('rejects signed download without a valid signature', function (): void {
    $shipment = Shipment::factory()->create();
    $document = ShipmentDocument::factory()->create(['shipment_id' => $shipment->id]);
    $file = ShipmentDocumentFile::factory()->create([
        'shipment_document_id' => $document->id,
        'path' => 'x/y.pdf',
    ]);

    $this->get("/shipments/{$shipment->id}/documents/files/{$file->id}/signed")
        ->assertForbidden();
});

it('returns not found when signed url targets wrong shipment for the file', function (): void {
    $shipmentA = Shipment::factory()->create();
    $shipmentB = Shipment::factory()->create();
    $relativePath = 'shipment-documents/'.$shipmentB->id.'/f.pdf';
    Storage::disk('local')->put($relativePath, 'x');

    $document = ShipmentDocument::factory()->create(['shipment_id' => $shipmentB->id]);
    $file = ShipmentDocumentFile::factory()->create([
        'shipment_document_id' => $document->id,
        'path' => $relativePath,
    ]);

    $url = URL::temporarySignedRoute(
        'shipments.documents.files.download.signed',
        now()->addHour(),
        ['shipment' => $shipmentA->id, 'file' => $file->id],
    );

    $this->get($url)->assertNotFound();
});

it('builds tracking presenter download links from document attach metadata', function (): void {
    $shipment = Shipment::factory()->create();
    $relativePath = 'shipment-documents/'.$shipment->id.'/a.pdf';
    Storage::disk('local')->put($relativePath, 'x');

    $document = ShipmentDocument::factory()->create(['shipment_id' => $shipment->id]);
    $file = ShipmentDocumentFile::factory()->create([
        'shipment_document_id' => $document->id,
        'path' => $relativePath,
        'original_name' => 'doc.pdf',
    ]);

    $tracking = new ShipmentTracking([
        'shipment_id' => $shipment->id,
        'status' => ShipmentStatus::CargoLoaded,
        'metadata' => [
            'source' => 'shipment_show_attach_document',
            'shipment_document_file_ids' => [$file->id],
            'file_names' => ['doc.pdf'],
            'document_type_label' => 'Bill of lading',
        ],
    ]);

    $badges = app(ShipmentTrackingPresenter::class)->badges($tracking, $shipment);
    $withHref = array_values(array_filter($badges, fn (array $b): bool => ! empty($b['href'])));

    expect($withHref)->not->toBeEmpty()
        ->and($withHref[0]['href'])->toContain('signature=')
        ->and($withHref[0]['href'])->toContain('/shipments/'.$shipment->id.'/documents/files/'.$file->id.'/signed');
});
