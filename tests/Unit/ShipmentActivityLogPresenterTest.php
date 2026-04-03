<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Support\ShipmentActivityLogPresenter;
use Tests\TestCase;

uses(TestCase::class);

it('titles invoice item added', function (): void {
    $log = new ActivityLog([
        'action' => 'invoice_item_added',
        'properties' => [],
    ]);

    expect((new ShipmentActivityLogPresenter)->title($log))->toBe(__('Invoice line added'));
});

it('shows description and amount for invoice item added', function (): void {
    $log = new ActivityLog([
        'action' => 'invoice_item_added',
        'properties' => [
            'description' => 'Ocean freight',
            'amount' => 99.5,
            'source' => 'shipment_show',
        ],
    ]);

    $texts = array_column((new ShipmentActivityLogPresenter)->badges($log), 'text');

    expect($texts)->toContain(__('Item: :i', ['i' => 'Ocean freight']))
        ->and($texts)->toContain(__('Amount: :a', ['a' => '$99.50']));
});

it('shows from and to for invoice item updated', function (): void {
    $log = new ActivityLog([
        'action' => 'invoice_item_updated',
        'properties' => [
            'from_description' => 'A',
            'to_description' => 'B',
            'from_amount' => 10.0,
            'to_amount' => 20.0,
            'source' => 'shipment_show',
        ],
    ]);

    $texts = array_column((new ShipmentActivityLogPresenter)->badges($log), 'text');

    expect($texts)->toContain(__('Item: :from → :to', ['from' => 'A', 'to' => 'B']))
        ->and($texts)->toContain(__('Amount: :from → :to', ['from' => '$10.00', 'to' => '$20.00']));
});
