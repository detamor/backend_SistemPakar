<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expert_consultations', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('whatsapp_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expert_consultations', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });
    }
};






