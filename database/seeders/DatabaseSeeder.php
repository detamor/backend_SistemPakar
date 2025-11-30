<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed data untuk testing
        $this->call([
            AdminUserSeeder::class, // Buat admin user
            PlantSeeder::class,
            SymptomSeeder::class,
            DiseaseSeeder::class,
            BonsaiDiseaseSeeder::class, // Gejala dan penyakit untuk Bonsai dengan CF
            CertaintyFactorLevelSeeder::class, // Bobot nilai CF
        ]);
    }
}
