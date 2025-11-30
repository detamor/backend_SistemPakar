<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\Disease;

class BonsaiDiseaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Data gejala dan penyakit untuk tanaman Bonsai berdasarkan metode Certainty Factor
     */
    public function run(): void
    {
        // Ambil tanaman Bonsai
        $bonsai = Plant::where('name', 'Bonsai')->first();
        
        if (!$bonsai) {
            $this->command->error('Tanaman Bonsai tidak ditemukan! Silakan jalankan PlantSeeder terlebih dahulu.');
            return;
        }

        // Ambil gejala yang sudah ada (gejala global untuk semua tanaman)
        // Gejala harus sudah dibuat di SymptomSeeder terlebih dahulu
        $symptomCodes = ['G1', 'G2', 'G3', 'G4', 'G5', 'G6', 'G7', 'G8', 'G9', 'G10', 'G11', 'G12', 'G13', 'G14', 'G15', 'G16', 'G17', 'G18'];
        $symptomModels = [];
        
        foreach ($symptomCodes as $code) {
            $symptom = Symptom::where('code', $code)->first();
            if ($symptom) {
                $symptomModels[$code] = $symptom;
            } else {
                $this->command->warn("Gejala dengan kode {$code} tidak ditemukan! Pastikan SymptomSeeder sudah dijalankan.");
            }
        }
        
        if (empty($symptomModels)) {
            $this->command->error('Tidak ada gejala yang ditemukan! Silakan jalankan SymptomSeeder terlebih dahulu.');
            return;
        }

        // Buat penyakit P1-P7 untuk Bonsai
        $diseases = [
            [
                'code' => 'P1',
                'name' => 'Kutu Daun',
                'description' => 'Hama kutu daun yang menyerang tanaman Bonsai',
                'cause' => 'Daunnya kering atau menggulung, daun dan batangnya lemah, daun berwarna coklat dan tidak ada pertumbuhan daun baru.',
                'solution' => 'Menyingkirkan tanaman yang sakit untuk sementara, jika tidak terlalu parah dapat diatasi dengan menyemprotkan air hangat seminggu sekali dan disemprot dengan obat.',
                'prevention' => 'Rutin menyemprotkan pestisida minimal dua minggu sekali, menjaga kebersihan tanaman, dan memantau pertumbuhan daun baru.',
            ],
            [
                'code' => 'P2',
                'name' => 'Ulat',
                'description' => 'Hama ulat yang menyerang daun Bonsai',
                'cause' => 'Berlubang lubang dan terdapat kotoran ulat di permukaannya',
                'solution' => 'Musnahkanlah ulatnya, usahakan mencabut gulma yang tumbuh disekitarnya agar hama tidak datang kembali.',
                'prevention' => 'Rutin memeriksa tanaman, membersihkan gulma, dan menyemprotkan pestisida preventif.',
            ],
            [
                'code' => 'P3',
                'name' => 'Thrips',
                'description' => 'Hama thrips yang menyerang tanaman Bonsai',
                'cause' => 'Kuncup gagal menjadi bunga dan akhirnya rontok, bercak-bercak pada daun, serta daun melepuh kemudian rontok.',
                'solution' => 'Bersihkan tanaman dengan air hangat dan semprotkan obat yang sesuai sebanyak satu kali seminggu selama dua 2 minggu.',
                'prevention' => 'Rutin menyemprotkan pestisida, menjaga kebersihan tanaman, dan memantau kondisi kuncup bunga.',
            ],
            [
                'code' => 'P4',
                'name' => 'Cabuk Putih',
                'description' => 'Hama cabuk putih yang menyerang tanaman Bonsai',
                'cause' => 'Permukaan atas ataupun bawah daun menjadi hitam dan dikerumuni semut. Dan terdapat telur lalat di bawah daun.',
                'solution' => 'Singkirkan semut yang ada pada tanaman dan bersihkan dengan air hangat, kemudian diberi obat yang sesuai.',
                'prevention' => 'Rutin memeriksa bagian bawah daun, membersihkan semut, dan menyemprotkan pestisida.',
            ],
            [
                'code' => 'P5',
                'name' => 'Jamur',
                'description' => 'Penyakit jamur pada tanaman Bonsai',
                'cause' => 'Tanaman kelihatan layu, mahkota rontok, pucuk daun keriting, daun sobek dan menguning, serta tangkai dan akar membusuk.',
                'solution' => 'Buanglah bagian tanaman yang rusak, kemudian semprotkan dengan air hangat.',
                'prevention' => 'Jaga kelembaban tidak berlebihan, pastikan sirkulasi udara baik, dan rutin menyemprotkan fungisida.',
            ],
            [
                'code' => 'P6',
                'name' => 'Cabuk Merah Pada batang',
                'description' => 'Hama cabuk merah yang menyerang batang Bonsai',
                'cause' => 'Terdapat hewan cabuk di dalam kulit batang, sehingga batang terlihat bintik-bintik merah.',
                'solution' => 'Sikatlah tanaman dengan sikat gigi dan insektisida.',
                'prevention' => 'Rutin memeriksa batang tanaman, menjaga kebersihan, dan menyemprotkan insektisida preventif.',
            ],
            [
                'code' => 'P7',
                'name' => 'Tumor Akar',
                'description' => 'Penyakit tumor akar pada tanaman Bonsai',
                'cause' => 'Tidak bisa tumbuh daun yang baru, kalau disiram airnya tidak cepat habis.',
                'solution' => 'Mengganti tanah dengan tanah yang baru.',
                'prevention' => 'Pastikan drainase baik, jangan overwatering, dan ganti media tanam secara berkala.',
            ],
        ];

        // Mapping CF berdasarkan tabel Certainty Factor Pakar
        $cfMapping = [
            'P1' => [
                'G1' => 0.6,
                'G2' => 0.8,
                'G3' => 0.8,
                'G4' => 0.6,
            ],
            'P2' => [
                'G5' => 1.0,
                'G6' => 0.8,
            ],
            'P3' => [
                'G7' => 0.6,
                'G8' => 0.8,
            ],
            'P4' => [
                'G9' => 1.0,
                'G10' => 0.6,
            ],
            'P5' => [
                'G11' => 0.8,
                'G12' => 1.0,
                'G13' => 0.6,
                'G14' => 1.0,
                'G15' => 0.8,
            ],
            'P6' => [
                'G16' => 0.8,
            ],
            'P7' => [
                'G4' => 0.6,
                'G17' => 0.6,
                'G18' => 0.6,
            ],
        ];

        // Buat penyakit dan attach gejala dengan CF
        foreach ($diseases as $diseaseData) {
            $disease = Disease::updateOrCreate(
                [
                    'code' => $diseaseData['code'],
                    'plant_id' => $bonsai->id,
                ],
                [
                    'name' => $diseaseData['name'],
                    'description' => $diseaseData['description'],
                    'cause' => $diseaseData['cause'],
                    'solution' => $diseaseData['solution'],
                    'prevention' => $diseaseData['prevention'],
                    'is_active' => true,
                ]
            );

            // Attach gejala dengan CF sesuai mapping
            if (isset($cfMapping[$diseaseData['code']])) {
                // Detach semua gejala yang sudah ada untuk penyakit ini
                $disease->symptoms()->detach();
                
                // Attach gejala baru dengan CF
                foreach ($cfMapping[$diseaseData['code']] as $symptomCode => $cf) {
                    if (isset($symptomModels[$symptomCode])) {
                        $disease->symptoms()->attach($symptomModels[$symptomCode]->id, [
                            'certainty_factor' => $cf
                        ]);
                    }
                }
            }
        }

        $this->command->info('Bonsai Disease Seeder completed!');
        $this->command->info(count($symptomModels) . ' symptoms used');
        $this->command->info(count($diseases) . ' diseases created/updated for Bonsai');
    }
}
