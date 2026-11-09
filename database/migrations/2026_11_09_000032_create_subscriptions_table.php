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
            $table->string('status', 20);
            $table->string('billing_period', 10);
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            // Pencarian langganan berjalan per tenant dan pekerjaan penjadwal per status.
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'trial_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
