<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // whatsapp_messages pada MVP.
            $table->string('metric', 30);
            // Format 2026-10 menurut zona waktu tenant.
            $table->char('period', 7);
            // Dinaikkan atomik lewat pembaruan bersyarat (lihat MessageQuota).
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
