<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CertaintyFactorLevel;

class CertaintyFactorLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            [
                'label' => 'Tidak Yakin',
                'value' => 0.0,
                'order' => 1,
                'is_active' => true,
            ],
            [
                'label' => 'Sedikit Yakin',
                'value' => 0.4,
                'order' => 2,
                'is_active' => true,
            ],
            [
                'label' => 'Cukup Yakin',
                'value' => 0.6,
                'order' => 3,
                'is_active' => true,
            ],
            [
                'label' => 'Yakin',
                'value' => 0.8,
                'order' => 4,
                'is_active' => true,
            ],
            [
                'label' => 'Sangat Yakin',
                'value' => 1.0,
                'order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($levels as $level) {
            CertaintyFactorLevel::updateOrCreate(
                ['label' => $level['label']],
                $level
            );
        }
    }
}

