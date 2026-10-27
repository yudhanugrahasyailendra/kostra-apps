<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->morphs('attachable');
            $table->string('category', 30);
            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('uploaded_by_resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
