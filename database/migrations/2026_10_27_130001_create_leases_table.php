<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            // property_id disalin dari kamar untuk mempercepat filter laporan.
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('primary_resident_id')->constrained('residents')->restrictOnDelete();
            $table->string('status', 20);
            // Diisi room_id selama kontrak aktif, null bila tidak. Unik: satu kontrak aktif per kamar.
            // Null boleh berulang pada indeks unik MySQL, jadi kontrak lama tidak saling menghalangi.
            $table->unsignedBigInteger('active_room_id')->nullable()->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('billing_cycle', 10);
            $table->unsignedInteger('rent_amount');
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->foreignId('renewed_from_lease_id')->nullable()->constrained('leases')->restrictOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->text('checkin_notes')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->text('checkout_notes')->nullable();
            $table->string('ended_reason', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
