<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gejala perlu penanda tanaman agar tetap muncul di admin walau semua
     * penyakit (dan baris pivot disease_symptoms) untuk tanaman itu sudah dihapus.
     */
    public function up(): void
    {
        Schema::table('symptoms', function (Blueprint $table) {
            $table->foreignId('plant_id')
                ->nullable()
                ->after('id')
                ->constrained('plants')
                ->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('
                UPDATE symptoms s
                INNER JOIN (
                    SELECT ds.symptom_id, MIN(d.plant_id) AS plant_id
                    FROM disease_symptoms ds
                    INNER JOIN diseases d ON d.id = ds.disease_id
                    GROUP BY ds.symptom_id
                ) x ON x.symptom_id = s.id
                SET s.plant_id = x.plant_id
                WHERE s.plant_id IS NULL
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('symptoms', function (Blueprint $table) {
            $table->dropForeign(['plant_id']);
            $table->dropColumn('plant_id');
        });
    }
};
