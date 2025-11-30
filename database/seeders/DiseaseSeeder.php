<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Disease;
use App\Models\Plant;
use App\Models\Symptom;

class DiseaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil tanaman dan gejala
        $anggrek = Plant::where('name', 'Anggrek')->first();
        $aglaonema = Plant::where('name', 'Aglaonema')->first();
        $monstera = Plant::where('name', 'Monstera')->first();

        // Ambil gejala (menggunakan kode G1-G18 yang sudah distandardisasi)
        $g01 = Symptom::where('code', 'G14')->first(); // Daun menguning dan sobek (G14)
        $g02 = Symptom::where('code', 'G11')->first(); // Tanaman layu (G11)
        $g03 = Symptom::where('code', 'G5')->first(); // Daunnya berlubang-lubang (G5)
        $g04 = Symptom::where('code', 'G2')->first(); // Daun berwarna coklat (G2)
        $g05 = Symptom::where('code', 'G1')->first(); // Daun kering atau menggulung (G1)
        $g06 = Symptom::where('code', 'G9')->first(); // Permukaan daun menjadi hitam (G9)
        $g07 = Symptom::where('code', 'G7')->first(); // Kuncup gagal menjadi bunga (G7)
        $g09 = Symptom::where('code', 'G15')->first(); // Tangkai dan akar membusuk (G15)
        $g10 = Symptom::where('code', 'G16')->first(); // Terdapat hewan cabuk di batang (G16) - untuk batang lunak bisa pakai G3
        $g12 = Symptom::where('code', 'G12')->first(); // Mahkota rontok (G12)

        // Penyakit untuk Anggrek
        if ($anggrek) {
            // Penyakit 1: Busuk Akar
            $disease1 = Disease::create([
                'name' => 'Busuk Akar',
                'code' => 'P01',
                'description' => 'Penyakit busuk akar pada anggrek disebabkan oleh jamur atau bakteri yang menyerang sistem akar',
                'cause' => 'Penyiraman berlebihan, drainase buruk, atau infeksi jamur/bakteri',
                'solution' => '1. Kurangi penyiraman\n2. Ganti media tanam\n3. Potong akar yang busuk\n4. Berikan fungisida\n5. Pastikan drainase baik',
                'prevention' => '1. Jangan overwatering\n2. Gunakan media tanam yang porous\n3. Pastikan pot memiliki lubang drainase\n4. Hindari genangan air',
                'plant_id' => $anggrek->id,
                'is_active' => true,
            ]);

            // Attach gejala dengan CF
            if ($g09) $disease1->symptoms()->attach($g09->id, ['certainty_factor' => 0.9]); // Akar membusuk
            if ($g02) $disease1->symptoms()->attach($g02->id, ['certainty_factor' => 0.7]); // Daun layu
            if ($g01) $disease1->symptoms()->attach($g01->id, ['certainty_factor' => 0.6]); // Daun menguning
            if ($g10) $disease1->symptoms()->attach($g10->id, ['certainty_factor' => 0.5]); // Batang lunak

            // Penyakit 2: Layu Fusarium
            $disease2 = Disease::create([
                'name' => 'Layu Fusarium',
                'code' => 'P02',
                'description' => 'Penyakit layu yang disebabkan oleh jamur Fusarium',
                'cause' => 'Infeksi jamur Fusarium melalui luka atau media tanam yang terkontaminasi',
                'solution' => '1. Isolasi tanaman yang terinfeksi\n2. Potong bagian yang terinfeksi\n3. Berikan fungisida sistemik\n4. Ganti media tanam\n5. Perbaiki sirkulasi udara',
                'prevention' => '1. Sterilkan alat potong\n2. Gunakan media tanam steril\n3. Hindari luka pada tanaman\n4. Jaga kebersihan lingkungan',
                'plant_id' => $anggrek->id,
                'is_active' => true,
            ]);

            if ($g02) $disease2->symptoms()->attach($g02->id, ['certainty_factor' => 0.9]); // Daun layu
            if ($g01) $disease2->symptoms()->attach($g01->id, ['certainty_factor' => 0.8]); // Daun menguning
            if ($g10) $disease2->symptoms()->attach($g10->id, ['certainty_factor' => 0.7]); // Batang lunak
            if ($g12) $disease2->symptoms()->attach($g12->id, ['certainty_factor' => 0.6]); // Daun rontok
        }

        // Penyakit untuk Aglaonema
        if ($aglaonema) {
            // Penyakit 3: Bercak Daun
            $disease3 = Disease::create([
                'name' => 'Bercak Daun',
                'code' => 'P03',
                'description' => 'Penyakit bercak pada daun aglaonema yang disebabkan oleh bakteri atau jamur',
                'cause' => 'Kelembaban tinggi, sirkulasi udara buruk, atau infeksi bakteri/jamur',
                'solution' => '1. Potong daun yang terinfeksi\n2. Kurangi kelembaban\n3. Perbaiki sirkulasi udara\n4. Berikan fungisida/bakterisida\n5. Hindari penyiraman pada daun',
                'prevention' => '1. Jaga sirkulasi udara baik\n2. Hindari kelembaban berlebihan\n3. Siram di pagi hari\n4. Jaga kebersihan daun',
                'plant_id' => $aglaonema->id,
                'is_active' => true,
            ]);

            if ($g06) $disease3->symptoms()->attach($g06->id, ['certainty_factor' => 0.9]); // Bintik hitam pada daun
            if ($g04) $disease3->symptoms()->attach($g04->id, ['certainty_factor' => 0.8]); // Daun berwarna coklat
            if ($g01) $disease3->symptoms()->attach($g01->id, ['certainty_factor' => 0.6]); // Daun menguning
            if ($g12) $disease3->symptoms()->attach($g12->id, ['certainty_factor' => 0.5]); // Daun rontok

            // Penyakit 4: Kekurangan Nutrisi
            $disease4 = Disease::create([
                'name' => 'Kekurangan Nutrisi',
                'code' => 'P04',
                'description' => 'Tanaman mengalami kekurangan nutrisi esensial',
                'cause' => 'Media tanam miskin nutrisi, tidak ada pemupukan, atau pH tidak sesuai',
                'solution' => '1. Berikan pupuk lengkap (NPK)\n2. Periksa pH media tanam\n3. Ganti media tanam jika perlu\n4. Berikan pupuk cair secara rutin\n5. Pastikan drainase baik',
                'prevention' => '1. Berikan pupuk rutin\n2. Periksa pH media tanam\n3. Ganti media tanam berkala\n4. Gunakan pupuk seimbang',
                'plant_id' => $aglaonema->id,
                'is_active' => true,
            ]);

            if ($g01) $disease4->symptoms()->attach($g01->id, ['certainty_factor' => 0.8]); // Daun menguning
            $g11 = Symptom::where('code', 'G11')->first(); // Tanaman layu
            if ($g11) $disease4->symptoms()->attach($g11->id, ['certainty_factor' => 0.7]); // Tanaman layu
            if ($g05) $disease4->symptoms()->attach($g05->id, ['certainty_factor' => 0.6]); // Daun kering atau menggulung
        }

        // Penyakit untuk Monstera
        if ($monstera) {
            // Penyakit 5: Busuk Batang
            $disease5 = Disease::create([
                'name' => 'Busuk Batang',
                'code' => 'P05',
                'description' => 'Penyakit busuk pada batang monstera',
                'cause' => 'Penyiraman berlebihan, infeksi jamur, atau luka pada batang',
                'solution' => '1. Potong bagian batang yang busuk\n2. Kurangi penyiraman\n3. Perbaiki drainase\n4. Berikan fungisida\n5. Pastikan sirkulasi udara baik',
                'prevention' => '1. Jangan overwatering\n2. Hindari luka pada batang\n3. Pastikan drainase baik\n4. Jaga kebersihan',
                'plant_id' => $monstera->id,
                'is_active' => true,
            ]);

            if ($g10) $disease5->symptoms()->attach($g10->id, ['certainty_factor' => 0.9]); // Batang lunak
            if ($g02) $disease5->symptoms()->attach($g02->id, ['certainty_factor' => 0.8]); // Daun layu
            if ($g01) $disease5->symptoms()->attach($g01->id, ['certainty_factor' => 0.7]); // Daun menguning
            if ($g03) $disease5->symptoms()->attach($g03->id, ['certainty_factor' => 0.5]); // Daun berlubang (bukan karena alami)
        }
    }
}
