<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipper_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('shipper_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipping_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_port_id')->constrained('ports')->restrictOnDelete();
            $table->foreignId('destination_port_id')->constrained('ports')->restrictOnDelete();
            $table->string('logistics_service');
            $table->string('shipping_mode');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('vin')->nullable()->index();
            $table->string('lot_number')->nullable()->index();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->string('series')->nullable();
            $table->string('body_style')->nullable();
            $table->string('color')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('transmission')->nullable();
            $table->string('fuel')->nullable();
            $table->string('engine_type')->nullable();
            $table->string('drive')->nullable();
            $table->unsignedInteger('cylinders')->nullable();
            $table->unsignedBigInteger('odometer')->nullable();
            $table->string('car_keys')->nullable();
            $table->string('doc_type')->nullable();
            $table->string('primary_damage')->nullable();
            $table->string('secondary_damage')->nullable();
            $table->string('highlights')->nullable();
            $table->string('location')->nullable();
            $table->string('auction_name')->nullable();
            $table->string('seller')->nullable();
            $table->decimal('est_retail_value', 14, 2)->nullable();
            $table->boolean('is_insurance')->default(false);
            $table->unsignedBigInteger('currency_code_id')->nullable();
            $table->json('api_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('shipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_document_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_document_files');
        Schema::dropIfExists('shipment_documents');
        Schema::dropIfExists('shipment_trackings');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('consignees');
    }
};
