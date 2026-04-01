<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\InvoiceItem;

it('persists invoice items with description and amount only', function () {
    $invoice = Invoice::factory()->create();

    $item = InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Freight charge',
        'amount' => 123.45,
    ]);

    $item->refresh();

    expect($item->description)->toBe('Freight charge')
        ->and((string) $item->amount)->toBe('123.45');

    $this->assertDatabaseHas('invoice_items', [
        'id' => $item->id,
        'invoice_id' => $invoice->id,
        'description' => 'Freight charge',
        'amount' => 123.45,
    ]);
});

it('factory creates valid invoice items', function () {
    $item = InvoiceItem::factory()->create();

    expect($item->invoice)->toBeInstanceOf(Invoice::class)
        ->and($item->description)->not->toBeEmpty()
        ->and((float) $item->amount)->toBeGreaterThan(0);
});
