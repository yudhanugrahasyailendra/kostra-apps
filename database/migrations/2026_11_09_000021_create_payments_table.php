<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            // Rekening tujuan; null untuk tunai.
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('status', 10);
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number', 30)->nullable();
            $table->timestamp('receipt_issued_at')->nullable();
            $table->timestamp('receipt_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['tenant_id', 'paid_on']);
            $table->index(['tenant_id', 'resident_id']);
            $table->index(['tenant_id', 'status', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
