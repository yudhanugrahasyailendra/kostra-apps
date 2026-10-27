<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            // Terisi bila penulisnya pemilik atau staf.
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            // Terisi bila penulisnya penghuni.
            $table->foreignId('resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['complaint_id', 'id']);
        });

        // Tepat satu dari user_id dan resident_id terisi: dijaga di database, bukan hanya di Action.
        DB::statement('ALTER TABLE complaint_comments ADD CONSTRAINT complaint_comments_one_author CHECK ((user_id IS NULL) <> (resident_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_comments');
    }
};
