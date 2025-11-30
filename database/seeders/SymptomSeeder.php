<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Symptom;

class SymptomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Gejala global untuk semua tanaman hias (sesuai metode Certainty Factor)
        $symptoms = [
            [
                'code' => 'G1',
                'description' => 'Daun kering atau menggulung',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G2',
                'description' => 'Daun berwarna coklat',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G3',
                'description' => 'Daun dan batangnya lemah',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G4',
                'description' => 'Tidak tumbuh daun yang baru',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G5',
                'description' => 'Daunnya berlubang-lubang',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G6',
                'description' => 'Terdapat Kotoran ulat di permukaannya',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G7',
                'description' => 'Kuncup gagal menjadi bunga dan rontok',
                'category' => 'Bunga',
                'is_active' => true,
            ],
            [
                'code' => 'G8',
                'description' => 'Bercak-bercak pada daun kemudian melepuh dan rontok',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G9',
                'description' => 'Permukaan daun atas maupun bawah menjadi hitam',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G10',
                'description' => 'Dikerumuni semut, kemudian terdapat telur lalat di permukaan bawah daun',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G11',
                'description' => 'Tanaman layu',
                'category' => 'Umum',
                'is_active' => true,
            ],
            [
                'code' => 'G12',
                'description' => 'Mahkota rontok',
                'category' => 'Bunga',
                'is_active' => true,
            ],
            [
                'code' => 'G13',
                'description' => 'Pucuk daun keriting',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G14',
                'description' => 'Daun menguning dan sobek',
                'category' => 'Daun',
                'is_active' => true,
            ],
            [
                'code' => 'G15',
                'description' => 'Tangkai dan akar membusuk',
                'category' => 'Akar',
                'is_active' => true,
            ],
            [
                'code' => 'G16',
                'description' => 'Terdapat hewan cabuk di dalam kulit batang',
                'category' => 'Batang',
                'is_active' => true,
            ],
            [
                'code' => 'G17',
                'description' => 'Kalau di siram air tidak cepat habis',
                'category' => 'Akar',
                'is_active' => true,
            ],
            [
                'code' => 'G18',
                'description' => 'Tidak tumbuh daun yang baru',
                'category' => 'Daun',
                'is_active' => true,
            ],
        ];

        foreach ($symptoms as $symptom) {
            Symptom::updateOrCreate(
                ['code' => $symptom['code']],
                $symptom
            );
        }
        
        $this->command->info('Symptom seeder completed! ' . count($symptoms) . ' symptoms created/updated.');
    }
}
