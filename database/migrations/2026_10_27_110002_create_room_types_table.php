<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            // Null = tipe berlaku untuk semua properti tenant.
            $table->foreignId('property_id')->nullable()->constrained('properties')->restrictOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('base_price');
            $table->decimal('size_m2', 5, 1)->nullable();
            $table->json('facilities')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
