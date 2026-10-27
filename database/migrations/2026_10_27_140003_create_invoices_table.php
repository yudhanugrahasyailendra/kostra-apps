<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
            // Penanggung jawab saat tagihan terbit.
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->string('number', 30);
            $table->string('type', 15);
            // Diisi untuk recurring. Baris manual (period null) tidak terkena batasan unik di bawah.
            $table->char('period', 7)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 20);
            $table->unsignedBigInteger('subtotal')->default(0);
            // Bisa negatif; total = subtotal + adjustment_total.
            $table->bigInteger('adjustment_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            // Null bila dibuat oleh penjadwal.
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Penerbitan bulanan idempoten: tidak ada dua tagihan bulanan untuk kontrak dan periode yang sama.
            $table->unique(['lease_id', 'period']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'due_date']);
            $table->index(['tenant_id', 'resident_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
