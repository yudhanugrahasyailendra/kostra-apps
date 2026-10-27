<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Setiap pesan keluar tercatat di sini, termasuk yang dilewati. Isinya berisi nomor dan teks akhir (bukti kirim).
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->restrictOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            // Tagihan, pembayaran, kontrak, atau komplain yang memicu pesan.
            $table->nullableMorphs('subject');
            $table->string('channel', 15);
            $table->string('category', 15);
            // Salinan nomor saat dikirim.
            $table->string('recipient', 20);
            $table->text('body');
            $table->string('status', 15);
            $table->string('skip_reason', 30)->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id', 100)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            // True bila memakai kuota usage_counters.
            $table->boolean('counted_in_quota')->default(false);
            $table->timestamps();

            $table->index('provider_message_id');
            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
