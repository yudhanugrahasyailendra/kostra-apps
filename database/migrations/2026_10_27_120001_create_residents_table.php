<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sengaja tidak ada kolom nomor KTP: hanya foto opsional lewat attachments.
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name', 120);
            // Nomor WhatsApp format internasional. Tidak unik agar duplikat bisa ditandai saat impor.
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('occupation', 120)->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_relation')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('whatsapp_opt_out_at')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
