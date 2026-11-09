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
            $table->unsignedTinyInteger('issue_day')->default(1);
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->boolean('prorate_first_month')->default(true);
            $table->boolean('prorate_last_month')->default(true);
            // Disiapkan untuk denda (tahap berikutnya); belum dipakai MVP.
            $table->string('late_fee_type', 10)->nullable();
            $table->unsignedInteger('late_fee_value')->nullable();
            $table->json('reminder_offsets');
            $table->time('send_window_start')->default('08:00:00');
            $table->time('send_window_end')->default('20:00:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
