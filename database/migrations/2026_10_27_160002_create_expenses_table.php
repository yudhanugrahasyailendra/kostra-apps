<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            // Null = pengeluaran umum semua properti.
            $table->foreignId('property_id')->nullable()->constrained('properties')->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            // Dasar pengelompokan laporan bulanan.
            $table->date('spent_on');
            $table->string('description', 255);
            $table->string('vendor_name', 120)->nullable();
            // Penyimpangan kecil dari docs/database.md: tabel complaints baru dibuat di Fase 8, jadi
            // kolom ini belum punya foreign key. Constraint-nya ditambahkan oleh migrasi Fase 8.
            $table->unsignedBigInteger('complaint_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'spent_on']);
            $table->index(['tenant_id', 'property_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
