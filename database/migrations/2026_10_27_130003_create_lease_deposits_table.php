<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deposit hanya dicatat (kewajiban yang dapat dikembalikan); Kostra tidak menahan dananya.
        Schema::create('lease_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('lease_id')->unique()->constrained('leases')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('received_at')->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('deduction_amount')->default(0);
            $table->text('deduction_note')->nullable();
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->date('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_deposits');
    }
};
