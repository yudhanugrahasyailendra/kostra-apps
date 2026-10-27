<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pembangkit nomor tanpa celah dan tanpa duplikat; dibaca dengan lockForUpdate dalam transaksi.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 20);
            $table->char('period', 7);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
