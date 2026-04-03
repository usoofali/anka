<?php

use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('authorized users can download the shipment invoice', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $shipment = Shipment::factory()->create(['reference_no' => 'SHIP-12345']);
    $invoice = Invoice::factory()->for($shipment)->create([
        'invoice_number' => 'INV-001',
        'subtotal' => 100.00,
        'tax_amount' => 10.00,
        'total_amount' => 110.00,
    ]);
    $invoice->items()->create(['description' => 'Test Item', 'amount' => 100.00]);

    $response = $this->actingAs($admin)
        ->get(route('shipments.invoice.download', $shipment));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'attachment; filename="Invoice:SHIP-12345.pdf"');
});

test('unauthorized users cannot download the shipment invoice', function () {
    $user = User::factory()->create();
    // No roles assigned

    $shipment = Shipment::factory()->create();
    Invoice::factory()->for($shipment)->create();

    $response = $this->actingAs($user)
        ->get(route('shipments.invoice.download', $shipment));

    // Shippers can only see their own shipments, others are forbidden
    $response->assertForbidden();
});

test('download fails if invoice does not exist', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $shipment = Shipment::factory()->create();
    // No invoice created

    $response = $this->actingAs($admin)
        ->get(route('shipments.invoice.download', $shipment));

    $response->assertNotFound();
});

test('can download invoice even with null dates', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $shipment = Shipment::factory()->create(['reference_no' => 'NULL-DATES']);
    $invoice = Invoice::factory()->for($shipment)->create([
        'issued_at' => null,
        'due_at' => null,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('shipments.invoice.download', $shipment));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'attachment; filename="Invoice:NULL-DATES.pdf"');
});
