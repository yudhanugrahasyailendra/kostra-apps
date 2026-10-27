<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->restrictOnDelete();
            $table->string('invoice_prefix', 10)->default('INV');
            $table->unsignedTinyInteger('issue_day');
            $table->unsignedTinyInteger('due_day');
            $table->boolean('prorate_first_month')->default(false);
            $table->boolean('prorate_last_month')->default(false);
            // Disiapkan untuk denda (tahap berikutnya); belum dipakai MVP.
            $table->string('late_fee_type', 10)->nullable();
            $table->unsignedInteger('late_fee_value')->nullable();
            // Dipakai pengingat WhatsApp (Fase 9).
            $table->json('reminder_offsets');
            $table->time('send_window_start');
            $table->time('send_window_end');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
