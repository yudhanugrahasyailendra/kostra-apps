<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->restrictOnDelete();
            $table->string('number', 20);
            $table->unsignedTinyInteger('floor')->nullable();
            $table->unsignedInteger('price');
            $table->string('status', 20);
            $table->json('facilities')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Keunikan nomor per properti dijaga validasi aplikasi (indeks ini tidak membedakan soft delete).
            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['property_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
