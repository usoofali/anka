<?php

declare(strict_types=1);

use App\Enums\ShipmentDocumentType;
use App\Models\ShipmentDocument;

it('persists document_type as a backed enum value', function () {
    $document = ShipmentDocument::factory()->create([
        'document_type' => ShipmentDocumentType::BillOfLading,
    ]);

    $document->refresh();

    expect($document->document_type)->toBe(ShipmentDocumentType::BillOfLading);

    $this->assertDatabaseHas('shipment_documents', [
        'id' => $document->id,
        'document_type' => ShipmentDocumentType::BillOfLading->value,
    ]);
});

it('factory assigns a valid shipment document type', function () {
    $document = ShipmentDocument::factory()->create();

    expect($document->document_type)->toBeInstanceOf(ShipmentDocumentType::class);
});
