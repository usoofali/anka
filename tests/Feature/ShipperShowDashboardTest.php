<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('allows staff to update shipper discount from the show page', function (): void {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $shipper = Shipper::factory()->create(['discount_amount' => 0]);

    actingAs($staffUser);

    Livewire::test('pages::shippers.show', ['shipper' => $shipper])
        ->call('openDiscountModal')
        ->set('discount_amount', '12.50')
        ->call('saveDiscount')
        ->assertHasNoErrors();

    $shipper->refresh();
    expect((string) $shipper->discount_amount)->toBe('12.50');
});

it('forbids shipper owner from saving discount on the show page', function (): void {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $owner->id]);

    actingAs($owner);

    Livewire::test('pages::shippers.show', ['shipper' => $shipper])
        ->call('saveDiscount')
        ->assertForbidden();
});

it('forbids shipper owner from opening the discount modal', function (): void {
    $owner = User::factory()->create();
    $owner->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $owner->id]);

    actingAs($owner);

    Livewire::test('pages::shippers.show', ['shipper' => $shipper])
        ->call('openDiscountModal')
        ->assertForbidden();
});

it('shows invoice payment summary figures for staff', function (): void {
    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $staffUser->id]);

    $shipper = Shipper::factory()->create();

    $paidShipment = Shipment::factory()->create(['shipper_id' => $shipper->id]);
    $paidInvoice = Invoice::factory()->create([
        'shipment_id' => $paidShipment->id,
        'total_amount' => 500,
    ]);
    Payment::factory()->create([
        'invoice_id' => $paidInvoice->id,
        'amount' => 450.25,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $openShipment = Shipment::factory()->create(['shipper_id' => $shipper->id]);
    Invoice::factory()->create([
        'shipment_id' => $openShipment->id,
        'total_amount' => 200.75,
    ]);

    actingAs($staffUser);

    $this->get(route('shippers.show', $shipper))
        ->assertSuccessful()
        ->assertSee('450.25')
        ->assertSee('200.75')
        ->assertSee(__('Total shipments'));
});
