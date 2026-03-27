<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('feedbacks')) {
            Schema::create('feedbacks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('diagnosis_id')->constrained('diagnoses')->cascadeOnDelete();
                $table->enum('accuracy', ['accurate', 'somewhat_accurate', 'inaccurate'])->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'diagnosis_id'], 'feedbacks_user_diagnosis_unique');
            });
            return;
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            if (!Schema::hasColumn('feedbacks', 'user_id')) {
                $table->unsignedBigInteger('user_id')->after('id');
            }

            if (!Schema::hasColumn('feedbacks', 'diagnosis_id')) {
                $table->unsignedBigInteger('diagnosis_id')->after('user_id');
            }

            if (!Schema::hasColumn('feedbacks', 'accuracy')) {
                $table->enum('accuracy', ['accurate', 'somewhat_accurate', 'inaccurate'])->nullable()->after('diagnosis_id');
            }

            if (!Schema::hasColumn('feedbacks', 'comment')) {
                $table->text('comment')->nullable()->after('accuracy');
            }
        });

        // Paksa tipe accuracy sesuai value yang dipakai aplikasi.
        try {
            DB::statement("ALTER TABLE feedbacks MODIFY accuracy ENUM('accurate','somewhat_accurate','inaccurate') NULL");
        } catch (\Throwable $e) {
            // Abaikan jika engine DB tidak mendukung perintah ini.
        }

        // Pastikan foreign key user_id -> users.id
        $userFkExists = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'feedbacks'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME = 'users'
            LIMIT 1
        ");

        if (!$userFkExists) {
            Schema::table('feedbacks', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Pastikan foreign key diagnosis_id -> diagnoses.id
        $diagnosisFkExists = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'feedbacks'
              AND COLUMN_NAME = 'diagnosis_id'
              AND REFERENCED_TABLE_NAME = 'diagnoses'
            LIMIT 1
        ");

        if (!$diagnosisFkExists) {
            Schema::table('feedbacks', function (Blueprint $table) {
                $table->foreign('diagnosis_id')->references('id')->on('diagnoses')->cascadeOnDelete();
            });
        }

        // Pastikan 1 user hanya punya 1 feedback per diagnosis.
        $uniqueExists = DB::selectOne("
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'feedbacks'
              AND INDEX_NAME = 'feedbacks_user_diagnosis_unique'
            LIMIT 1
        ");

        if (!$uniqueExists) {
            Schema::table('feedbacks', function (Blueprint $table) {
                $table->unique(['user_id', 'diagnosis_id'], 'feedbacks_user_diagnosis_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('feedbacks')) {
            return;
        }

        Schema::table('feedbacks', function (Blueprint $table) {
            try {
                $table->dropUnique('feedbacks_user_diagnosis_unique');
            } catch (\Throwable $e) {
                // Abaikan jika tidak ada.
            }
        });
    }
};
