<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan field untuk menyimpan data lengkap dari Python engine:
     * - recommendation: Rekomendasi dari sistem pakar
     * - all_possibilities_json: Semua kemungkinan penyakit (JSON)
     * - matched_symptoms_count: Jumlah gejala yang match
     */
    public function up(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->text('recommendation')->nullable()->after('certainty_value');
            $table->json('all_possibilities_json')->nullable()->after('recommendation');
            $table->integer('matched_symptoms_count')->nullable()->after('all_possibilities_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropColumn([
                'recommendation',
                'all_possibilities_json',
                'matched_symptoms_count'
            ]);
        });
    }
};

