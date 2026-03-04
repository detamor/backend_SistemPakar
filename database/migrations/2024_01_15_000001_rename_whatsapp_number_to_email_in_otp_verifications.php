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
        Schema::table('otp_verifications', function (Blueprint $table) {
            // Rename whatsapp_number to email
            $table->renameColumn('whatsapp_number', 'email');
        });

        // Update index (drop old, create new)
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_number', 'otp_code']);
            $table->index(['email', 'otp_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->renameColumn('email', 'whatsapp_number');
        });

        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropIndex(['email', 'otp_code']);
            $table->index(['whatsapp_number', 'otp_code']);
        });
    }
};
