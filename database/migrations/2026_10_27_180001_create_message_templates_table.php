<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            // Null = template bawaan sistem yang diisi seeder; terisi = salinan milik satu tenant.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('channel', 15)->default('whatsapp');
            $table->string('name', 120);
            $table->text('body');
            // Selalu utility: teks bernada promosi tidak diizinkan.
            $table->string('category', 15)->default('utility');
            $table->string('provider_template_name', 100)->nullable();
            $table->string('provider_status', 15)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Tambahan atas docs/database.md: satu salinan per tenant dan kunci template.
            $table->unique(['tenant_id', 'key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
