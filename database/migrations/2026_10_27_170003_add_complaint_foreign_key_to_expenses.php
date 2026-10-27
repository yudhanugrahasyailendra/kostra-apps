<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menutup penyimpangan Fase 7: expenses.complaint_id dibuat tanpa foreign key karena complaints belum ada.
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('complaint_id')->references('id')->on('complaints')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['complaint_id']);
        });
    }
};
