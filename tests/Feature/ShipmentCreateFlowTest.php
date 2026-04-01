<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\LogisticsService;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Models\ActivityLog;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\DefaultShipmentSetting;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Port;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Shipper;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\ShipmentCreatedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('creates shipment side-effects with aligned payload fields', function () {
    Notification::fake();

    $user = User::factory()->create();
    actingAs($user);

    $shipper = Shipper::factory()->create();
    $consignee = Consignee::factory()->for($shipper)->create();
    $carrier = Carrier::factory()->create();
    $originPort = Port::factory()->create();
    $destinationPort = Port::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    DefaultShipmentSetting::current()->update([
        'origin_port_id' => $originPort->id,
        'logistics_service' => LogisticsService::Ocean->value,
        'shipping_mode' => ShippingMode::Roro->value,
        'shipment_status' => ShipmentStatus::Pending->value,
        'invoice_status' => InvoiceStatus::Draft->value,
        'payment_status' => PaymentStatus::Pending->value,
        'payment_method_id' => $paymentMethod->id,
    ]);

    SystemSetting::current()->update([
        'tracking_delivery_prefix' => 'SHP',
        'tracking_number_type' => 'auto_increment',
        'tracking_digits' => 5,
    ]);

    Livewire::test('pages::shipments.create')
        ->set('shipper_id', $shipper->id)
        ->set('consignee_id', $consignee->id)
        ->set('vin', '2T1BURHE7FC251274')
        ->set('carrier_id', $carrier->id)
        ->set('origin_port_id', $originPort->id)
        ->set('destination_port_id', $destinationPort->id)
        ->set('logistics_service', LogisticsService::Ocean->value)
        ->set('shipping_mode', ShippingMode::Roro->value)
        ->set('shipment_status', ShipmentStatus::Pending->value)
        ->set('invoice_status', InvoiceStatus::Draft->value)
        ->set('payment_status', PaymentStatus::Pending->value)
        ->call('save')
        ->assertHasNoErrors();

    $shipment = Shipment::query()->firstOrFail();
    $tracking = ShipmentTracking::query()->where('shipment_id', $shipment->id)->firstOrFail();
    $invoice = Invoice::query()->where('shipment_id', $shipment->id)->firstOrFail();
    $activityLog = ActivityLog::query()->where('shipment_id', $shipment->id)->firstOrFail();

    expect($shipment->payment_method_id)->toBe($paymentMethod->id)
        ->and($tracking->note)->toBe('Initial record created.')
        ->and($tracking->metadata)->toBeArray()
        ->and($tracking->metadata['source'] ?? null)->toBe('shipment_create')
        ->and($invoice->subtotal)->toBe('0.00')
        ->and($invoice->tax_amount)->toBe('0.00')
        ->and($invoice->total_amount)->toBe('0.00')
        ->and($activityLog->action)->toBe('created')
        ->and($activityLog->properties)->toBeArray()
        ->and($activityLog->properties['source'] ?? null)->toBe('shipment_create');

    Notification::assertSentTo(
        [$shipper->user],
        ShipmentCreatedNotification::class
    );
});
