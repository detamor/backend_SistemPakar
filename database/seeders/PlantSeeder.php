<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plant;

class PlantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    /**
     * Run the database seeds.
     * Tanaman hias sesuai observasi di Parongpong
     */
    public function run(): void
    {
        $plants = [
            [
                'name' => 'Mawar',
                'scientific_name' => 'Rosa',
                'description' => 'Tanaman hias bunga mawar dengan berbagai warna dan aroma yang harum. Populer di Parongpong untuk kebutuhan wisata, proyek perumahan, dan penghobi.',
                'care_guide' => 'Mawar membutuhkan sinar matahari langsung minimal 6 jam per hari, penyiraman rutin namun tidak berlebihan, pemupukan berkala, dan penyemprotan pestisida minimal dua minggu sekali untuk mencegah hama dan penyakit.',
                'is_active' => true,
            ],
            [
                'name' => 'Azalea',
                'scientific_name' => 'Rhododendron',
                'description' => 'Tanaman hias berbunga dengan warna cerah dan mencolok. Banyak ditanam di Parongpong karena keindahan bunganya yang menarik perhatian.',
                'care_guide' => 'Azalea menyukai tempat teduh dengan sinar matahari tidak langsung, tanah asam yang lembab, kelembaban udara tinggi, penyiraman rutin, dan perawatan yang terstandar untuk menjaga kualitas tanaman.',
                'is_active' => true,
            ],
            [
                'name' => 'Palem Sikas',
                'scientific_name' => 'Cycas revoluta',
                'description' => 'Tanaman hias palem sikas dengan daun hijau mengkilap yang tahan lama. Sangat populer untuk dekorasi dan proyek lansekap.',
                'care_guide' => 'Palem Sikas membutuhkan cahaya terang tidak langsung, penyiraman sedang dengan air bersih, ventilasi yang baik, jarak tanam yang tidak terlalu rapat, dan perawatan terstandar untuk mencegah penyakit.',
                'is_active' => true,
            ],
            [
                'name' => 'Chrysanthemum',
                'scientific_name' => 'Chrysanthemum',
                'description' => 'Tanaman hias bunga krisan dengan berbagai varietas dan warna. Merupakan salah satu tanaman hias penting di Parongpong dengan permintaan pasar yang tinggi di Jawa Barat.',
                'care_guide' => 'Chrysanthemum membutuhkan intensitas cahaya yang cukup (tidak terlalu terang), jarak tanam yang optimal, penyiraman rutin dengan air bersih, penyemprotan pestisida minimal dua minggu sekali untuk mencegah hama seperti kutudaun, dan disiplin tenaga kerja dalam perawatan.',
                'is_active' => true,
            ],
            [
                'name' => 'Hydrangea',
                'scientific_name' => 'Hydrangea macrophylla',
                'description' => 'Tanaman hias berbunga dengan cluster bunga yang besar dan menarik. Populer untuk kebutuhan dekorasi dan penghobi tanaman hias.',
                'care_guide' => 'Hydrangea membutuhkan tempat teduh dengan cahaya tidak langsung, tanah lembab dengan drainase baik, penyiraman rutin, kelembaban udara tinggi, dan perawatan yang terstandar untuk mencegah penyakit dan menjaga kualitas bunga.',
                'is_active' => true,
            ],
            [
                'name' => 'Bonsai',
                'scientific_name' => 'Bonsai',
                'description' => 'Tanaman hias bonsai dengan teknik pemangkasan dan pembentukan yang menghasilkan pohon miniatur yang indah. Bonsai merupakan seni tanaman hias yang memerlukan perawatan khusus dan ketelitian tinggi.',
                'care_guide' => 'Bonsai membutuhkan perawatan intensif dengan penyiraman rutin namun tidak berlebihan, pemangkasan berkala untuk menjaga bentuk, pemupukan teratur, penggantian tanah secara berkala, penempatan yang tepat sesuai kebutuhan cahaya, dan penyemprotan pestisida minimal dua minggu sekali untuk mencegah hama dan penyakit seperti kutu daun, ulat, thrips, cabuk, jamur, dan tumor akar.',
                'is_active' => true,
            ],
        ];

        foreach ($plants as $plant) {
            Plant::updateOrCreate(
                ['name' => $plant['name']],
                $plant
            );
        }
        
        $this->command->info('Plant seeder completed! ' . count($plants) . ' plants seeded.');
    }
}
