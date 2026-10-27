<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            // Hanya hash SHA-256 yang disimpan; token asli hanya ada di tautan WhatsApp.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            // Pemilik dapat mencabut tautan.
            $table->timestamp('revoked_at')->nullable();
            // Null bila dibuat otomatis (mis. saat pesan dikirim).
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_tokens');
    }
};
