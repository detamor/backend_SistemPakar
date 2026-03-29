<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE otp_verifications MODIFY COLUMN type ENUM('registration','password_reset','email_change') NOT NULL DEFAULT 'registration'");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE otp_verifications MODIFY COLUMN type ENUM('registration','password_reset') NOT NULL DEFAULT 'registration'");
    }
};

