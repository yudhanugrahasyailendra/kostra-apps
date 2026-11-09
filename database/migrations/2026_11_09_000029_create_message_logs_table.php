<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->restrictOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            // Tagihan, pembayaran, atau kontrak yang memicu pesan. nullableMorphs membuat indeks (subject_type, subject_id).
            $table->nullableMorphs('subject');
            $table->string('channel', 15);
            $table->string('category', 15);
            $table->string('recipient', 20);
            $table->text('body');
            $table->string('status', 15);
            $table->string('skip_reason', 30)->nullable();
            $table->string('provider', 30);
            $table->string('provider_message_id', 100)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('error_message', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->boolean('counted_in_quota')->default(false);
            $table->timestamps();

            $table->index('provider_message_id');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
