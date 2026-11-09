<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on')->nullable();
            $table->string('sender_note', 255)->nullable();
            $table->string('status', 15);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
