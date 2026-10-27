<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            // Tepat satu per kontrak; sinkron dengan leases.primary_resident_id (dijaga Action).
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['lease_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_residents');
    }
};
