<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan tabel ada
        if (!Schema::hasTable('feedbacks')) {
            Schema::create('feedbacks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('diagnosis_id');
                $table->enum('accuracy', ['accurate', 'somewhat_accurate', 'inaccurate'])->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        // Tambahkan foreign key jika belum ada
        Schema::table('feedbacks', function (Blueprint $table) {
            try {
                // Cek dan tambahkan foreign key untuk user_id
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'feedbacks' 
                    AND COLUMN_NAME = 'user_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                if (empty($foreignKeys)) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                }
            } catch (\Exception $e) {
                // Foreign key mungkin sudah ada, skip
            }

            try {
                // Cek dan tambahkan foreign key untuk diagnosis_id
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'feedbacks' 
                    AND COLUMN_NAME = 'diagnosis_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                if (empty($foreignKeys)) {
                    $table->foreign('diagnosis_id')->references('id')->on('diagnoses')->onDelete('cascade');
                }
            } catch (\Exception $e) {
                // Foreign key mungkin sudah ada, skip
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            try {
                $table->dropForeign(['user_id']);
            } catch (\Exception $e) {
                // Foreign key mungkin tidak ada, skip
            }
            
            try {
                $table->dropForeign(['diagnosis_id']);
            } catch (\Exception $e) {
                // Foreign key mungkin tidak ada, skip
            }
        });
    }
};
