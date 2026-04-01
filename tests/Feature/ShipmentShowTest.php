<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\ChargeItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\InvoiceStatusChangedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('updates invoice and shipment invoice status, logs activity, and notifies staff but not shipper', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->givePermissionTo('invoices.manage');
    actingAs($user);

    $staffRecipient = User::factory()->create();
    Staff::factory()->create(['user_id' => $staffRecipient->id]);

    $shipment = Shipment::factory()->create([
        'invoice_status' => InvoiceStatus::Draft,
    ]);

    $shipperUser = $shipment->shipper->user;

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->call('openInvoiceStatusConfirm', InvoiceStatus::Cleared->value)
        ->assertSet('showInvoiceStatusConfirmModal', true)
        ->assertSet('pendingInvoiceStatus', InvoiceStatus::Cleared->value)
        ->call('confirmInvoiceStatusChange')
        ->assertHasNoErrors()
        ->assertSet('showInvoiceStatusConfirmModal', false);

    $shipment->refresh();
    expect($shipment->invoice_status)->toBe(InvoiceStatus::Cleared);

    $invoice = $shipment->invoice;
    expect($invoice)->not->toBeNull();
    expect($invoice->status)->toBe(InvoiceStatus::Cleared);

    $this->assertDatabaseHas('activity_logs', [
        'shipment_id' => $shipment->id,
        'user_id' => $user->id,
        'action' => 'invoice_status_changed',
    ]);

    Notification::assertSentTo($staffRecipient, InvoiceStatusChangedNotification::class);
    Notification::assertNotSentTo($shipperUser, InvoiceStatusChangedNotification::class);
});

it('forbids opening invoice status confirm without invoices.manage permission', function () {
    $user = User::factory()->create();
    actingAs($user);

    $shipment = Shipment::factory()->create();

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->call('openInvoiceStatusConfirm', InvoiceStatus::Cleared->value)
        ->assertForbidden();
});

it('adds an invoice item using a charge item code', function () {
    $user = User::factory()->create();
    actingAs($user);

    $charge = ChargeItem::factory()->create([
        'item' => 'Freight',
        'description' => 'Ocean freight line item',
    ]);

    $shipment = Shipment::factory()->create();

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('item_description', $charge->item)
        ->set('item_amount', '150.50')
        ->call('addOrUpdateItem')
        ->assertHasNoErrors();

    expect(InvoiceItem::query()->where('description', $charge->item)->where('amount', 150.5)->exists())->toBeTrue();
});

it('deletes an invoice item', function () {
    $user = User::factory()->create();
    actingAs($user);

    $shipment = Shipment::factory()->create();
    $invoice = Invoice::factory()->create(['shipment_id' => $shipment->id]);
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Removable line',
        'amount' => 40,
    ]);
    $shipment->refresh();

    Livewire::test('pages::shipments.show', ['shipment' => $shipment])
        ->call('deleteItem', $item->id)
        ->assertHasNoErrors();

    expect(InvoiceItem::query()->whereKey($item->id)->exists())->toBeFalse();
});
