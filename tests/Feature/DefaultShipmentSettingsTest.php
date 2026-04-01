<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\LogisticsService;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Models\Carrier;
use App\Models\DefaultShipmentSetting;
use App\Models\PaymentMethod;
use App\Models\Port;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('renders the default shipment settings page', function () {
    /** @var Authenticatable&User $user */
    $user = User::factory()->create();
    $user->givePermissionTo('default_shipment_settings.view');

    actingAs($user)
        ->get(route('default-shipment-settings.index'))
        ->assertOk()
        ->assertSee('Default Shipment Settings');
});

it('can update default shipment settings', function () {
    /** @var Authenticatable&User $user */
    $user = User::factory()->create();
    $user->givePermissionTo(['default_shipment_settings.view', 'default_shipment_settings.update']);

    $carrier = Carrier::factory()->create();
    $port = Port::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    actingAs($user);

    Livewire::test('pages::default-shipment-settings.index')
        ->set('carrier_id', $carrier->id)
        ->set('origin_port_id', $port->id)
        ->set('logistics_service', LogisticsService::Ocean->value)
        ->set('shipping_mode', ShippingMode::Roro->value)
        ->set('shipment_status', ShipmentStatus::Pending->value)
        ->set('invoice_status', InvoiceStatus::Draft->value)
        ->set('payment_status', PaymentStatus::Pending->value)
        ->set('payment_method_id', $paymentMethod->id)
        ->call('save')
        ->assertHasNoErrors();

    $setting = DefaultShipmentSetting::current();

    expect($setting->carrier_id)->toBe($carrier->id)
        ->and($setting->origin_port_id)->toBe($port->id)
        ->and($setting->logistics_service)->toBe(LogisticsService::Ocean)
        ->and($setting->shipping_mode)->toBe(ShippingMode::Roro)
        ->and($setting->shipment_status)->toBe(ShipmentStatus::Pending)
        ->and($setting->invoice_status)->toBe(InvoiceStatus::Draft)
        ->and($setting->payment_status)->toBe(PaymentStatus::Pending)
        ->and($setting->payment_method_id)->toBe($paymentMethod->id);
});
