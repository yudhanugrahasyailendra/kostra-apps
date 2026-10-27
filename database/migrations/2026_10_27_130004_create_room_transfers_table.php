<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
            $table->foreignId('from_room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('to_room_id')->constrained('rooms')->restrictOnDelete();
            $table->date('transferred_on');
            $table->unsignedInteger('new_rent_amount')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_transfers');
    }
};
