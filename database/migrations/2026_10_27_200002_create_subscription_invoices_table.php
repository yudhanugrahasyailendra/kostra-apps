<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tagihan langganan Kostra ke pemilik. Terpisah dari tagihan penghuni dan nomornya unik di seluruh platform.
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('amount');
            // pending, paid, void
            $table->string('status', 20);
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            // Transfer manual pada MVP.
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['subscription_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
