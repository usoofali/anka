<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('shipping_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('charge_items', function (Blueprint $table) {
            $table->id();
            $table->string('item');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('charge_items');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('workshops');
        Schema::dropIfExists('shipping_companies');
        Schema::dropIfExists('ports');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
