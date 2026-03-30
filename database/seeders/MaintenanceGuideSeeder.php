<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MaintenanceGuideSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\EducationalModule::updateOrCreate(
            ['title' => 'Panduan Optimalisasi Pemeliharaan Anggrek'],
            [
                'content' => "Panduan langkah demi langkah untuk memastikan Anggrek Anda tumbuh subur dan berbunga indah.\n\nAnggrek memerlukan perhatian khusus pada akar dan kelembapan udara. Pastikan media tanam memiliki sirkulasi udara yang baik.",
                'category' => 'Pemeliharaan',
                'is_maintenance_guide' => true,
                'watering_info' => '2-3 kali seminggu (pagi hari)',
                'light_info' => 'Cahaya terang tidak langsung',
                'humidity_info' => 'Tinggi (60-80%)',
                'difficulty' => 'Sedang',
                'maintenance_steps_json' => [
                    ['step' => 1, 'title' => 'Pemilihan Lokasi', 'description' => 'Letakkan di dekat jendela yang menghadap ke timur atau di bawah naungan jaring peneduh.'],
                    ['step' => 2, 'title' => 'Penyiraman Benar', 'description' => 'Siram saat media tanam mulai kering. Hindari menyiram bagian mahkota bunga.'],
                    ['step' => 3, 'title' => 'Pemupukan Rutin', 'description' => 'Gunakan pupuk khusus anggrek dengan dosis rendah setiap minggu sekali.'],
                    ['step' => 4, 'title' => 'Reposisi Pot', 'description' => 'Lakukan repotting setiap 1-2 tahun sekali saat akar sudah memenuhi pot atau media mulai hancur.']
                ],
                'is_active' => true
            ]
        );

        \App\Models\EducationalModule::updateOrCreate(
            ['title' => 'Seni Merawat Bonsai Terstruktur'],
            [
                'content' => "Mengoptimalkan pembentukan batang dan kesehatan akar bonsai untuk pemula.\n\nBonsai bukan sekadar tanaman kecil, melainkan karya seni yang hidup yang membutuhkan pemeliharaan presisi.",
                'category' => 'Pemeliharaan',
                'is_maintenance_guide' => true,
                'watering_info' => 'Setiap hari (jaga kelembapan)',
                'light_info' => 'Sinar matahari penuh (4-6 jam)',
                'humidity_info' => 'Sedang',
                'difficulty' => 'Sulit',
                'maintenance_steps_json' => [
                    ['step' => 1, 'title' => 'Penyiraman', 'description' => 'Jangan biarkan media benar-benar kering. Gunakan sprayer halus.'],
                    ['step' => 2, 'title' => 'Pemangkasan', 'description' => 'Lakukan pemangkasan rutin pada tunas baru untuk menjaga bentuk estetika.'],
                    ['step' => 3, 'title' => 'Kawat (Wiring)', 'description' => 'Lakukan pengawatan saat batang masih fleksibel untuk mengarahkan pertumbuhan.'],
                    ['step' => 4, 'title' => 'Media Tanam', 'description' => 'Gunakan campuran akadama, batu apung, dan lava rock untuk drainase optimal.']
                ],
                'is_active' => true
            ]
        );

        \App\Models\EducationalModule::updateOrCreate(
            ['title' => 'Rahasia Mawar Subur & Anti-Hama'],
            [
                'content' => "Meningkatkan intensitas pembungaan dan ketahanan terhadap penyakit pada tanaman Mawar.\n\nMawar membutuhkan nutrisi yang kaya dan perlindungan dari jamur daun.",
                'category' => 'Pemeliharaan',
                'is_maintenance_guide' => true,
                'watering_info' => 'Setiap pagi (hindari membasahi daun)',
                'light_info' => 'Matahari langsung (Minimal 6 jam)',
                'humidity_info' => 'Rendah - Sedang',
                'difficulty' => 'Mudah',
                'maintenance_steps_json' => [
                    ['step' => 1, 'title' => 'Sinar Matahari', 'description' => 'Pastikan mawar mendapatkan sinar matahari pagi minimal 6 jam sehari.'],
                    ['step' => 2, 'title' => 'Deadheading', 'description' => 'Potong bunga yang sudah layu untuk merangsang tunas bunga baru.'],
                    ['step' => 3, 'title' => 'Nutrisi Tanah', 'description' => 'Berikan pupuk organik NPK setiap 2 minggu sekali secara rutin.'],
                    ['step' => 4, 'title' => 'Control Hama', 'description' => 'Cek bagian bawah daun secara rutin untuk mendeteksi hama sejak dini.']
                ],
                'is_active' => true
            ]
        );
    }
}
