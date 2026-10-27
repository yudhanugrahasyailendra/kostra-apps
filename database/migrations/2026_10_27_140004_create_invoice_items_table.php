<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('description', 255);
            // Pecahan dipakai meteran pada tahap berikutnya. Ini jumlah, bukan uang.
            $table->decimal('quantity', 10, 2)->default(1);
            $table->unsignedBigInteger('unit_price');
            // quantity x unit_price, dibulatkan ke rupiah.
            $table->unsignedBigInteger('amount');
            $table->json('meta')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
