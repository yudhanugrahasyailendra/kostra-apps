<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            // trialing, active, past_due, canceled, expired
            $table->string('status', 20);
            // monthly atau yearly
            $table->string('billing_period', 10);
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('current_period_start');
            // Penyimpangan dari docs/database.md (date, wajib): paket Gratis tidak punya akhir periode, jadi nullable.
            $table->date('current_period_end')->nullable();
            // Masa tenggang 7 hari dan waktu pembatalan.
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
