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
            // Null = template bawaan sistem yang diisi seeder.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('channel', 15);
            $table->string('name', 120);
            $table->text('body');
            $table->string('category', 15);
            $table->string('provider_template_name', 100)->nullable();
            $table->string('provider_status', 15)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
