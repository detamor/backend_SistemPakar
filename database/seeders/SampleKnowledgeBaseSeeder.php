<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plant;
use App\Models\Disease;
use App\Models\Symptom;
use App\Models\CertaintyFactorLevel;
use Illuminate\Support\Facades\DB;

class SampleKnowledgeBaseSeeder extends Seeder
{
    /**
     * Seed sample knowledge base data for testing.
     * 
     * Tanaman: Hydrangea macrophylla & Rosa Tineke
     * Berdasarkan data riset penyakit tanaman hias
     */
    public function run(): void
    {
        // ============================================
        // 1. CF LEVELS (Bobot Nilai User)
        // ============================================
        $cfLevels = [
            ['label' => 'Tidak Yakin',    'value' => 0.0, 'order' => 1, 'is_active' => true],
            ['label' => 'Sedikit Yakin',  'value' => 0.4, 'order' => 2, 'is_active' => true],
            ['label' => 'Cukup Yakin',    'value' => 0.6, 'order' => 3, 'is_active' => true],
            ['label' => 'Yakin',          'value' => 0.8, 'order' => 4, 'is_active' => true],
            ['label' => 'Sangat Yakin',   'value' => 1.0, 'order' => 5, 'is_active' => true],
        ];

        foreach ($cfLevels as $level) {
            CertaintyFactorLevel::updateOrCreate(
                ['label' => $level['label']],
                $level
            );
        }

        $this->command->info('✅ CF Levels seeded (5 levels)');

        // ============================================
        // 2. TANAMAN (Plants)
        // ============================================
        $hydrangea = Plant::updateOrCreate(
            ['name' => 'Bigleaf Hydrangea'],
            [
                'scientific_name' => 'Hydrangea macrophylla',
                'description' => 'Tanaman hias populer dengan bunga besar berbentuk bola yang berwarna biru, pink, atau putih. Banyak dibudidayakan di Parongpong karena iklim sejuk yang cocok. Membutuhkan tanah lembab, teduh parsial, dan perawatan rutin.',
                'care_guide' => 'Siram secara teratur, jaga kelembaban tanah. Tempatkan di area teduh parsial dengan cahaya pagi. Pemupukan sebulan sekali dengan pupuk asam. Pangkas bunga yang sudah layu. Semprot pestisida minimal 2 minggu sekali.',
                'is_active' => true,
            ]
        );

        $rosaTineke = Plant::updateOrCreate(
            ['name' => 'Rosa Tineke'],
            [
                'scientific_name' => 'Rosa hybrid (Tineke)',
                'description' => 'Mawar Tineke adalah varietas Hybrid Tea dengan bunga putih kristal yang besar dan elegan. Populer sebagai bunga potong dan tanaman hias. Memiliki daun hijau tua dan relatif tahan penyakit, namun tetap membutuhkan perawatan optimal.',
                'care_guide' => 'Tanam di area dengan sinar matahari penuh (6-8 jam). Siram di pangkal tanaman, hindari membasahi daun. Pangkas rutin untuk sirkulasi udara. Berikan pupuk khusus mawar sebulan sekali. Semprot fungisida preventif setiap 2 minggu.',
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Plants seeded (2 tanaman)');

        // ============================================
        // 3. GEJALA (Symptoms)
        // ============================================
        $symptomsData = [
            // Gejala umum daun
            ['code' => 'G01', 'description' => 'Bercak coklat pada daun', 'category' => 'Daun'],
            ['code' => 'G02', 'description' => 'Daun menguning', 'category' => 'Daun'],
            ['code' => 'G03', 'description' => 'Daun rontok prematur', 'category' => 'Daun'],
            ['code' => 'G04', 'description' => 'Bercak hitam bulat pada daun dengan tepi kuning', 'category' => 'Daun'],
            ['code' => 'G05', 'description' => 'Lapisan tepung putih pada permukaan daun', 'category' => 'Daun'],
            ['code' => 'G06', 'description' => 'Daun mengkerut atau berkerut', 'category' => 'Daun'],
            ['code' => 'G07', 'description' => 'Daun layu meski sudah disiram', 'category' => 'Daun'],
            ['code' => 'G08', 'description' => 'Bintik karat oranye pada bagian bawah daun', 'category' => 'Daun'],
            ['code' => 'G09', 'description' => 'Daun sobek atau berlubang', 'category' => 'Daun'],

            // Gejala bunga
            ['code' => 'G10', 'description' => 'Bunga membusuk berwarna coklat', 'category' => 'Bunga'],
            ['code' => 'G11', 'description' => 'Kuncup bunga gagal mekar dan rontok', 'category' => 'Bunga'],
            ['code' => 'G12', 'description' => 'Bunga ditutupi jamur abu-abu berbulu', 'category' => 'Bunga'],
            ['code' => 'G13', 'description' => 'Mahkota bunga rontok', 'category' => 'Bunga'],

            // Gejala batang dan akar
            ['code' => 'G14', 'description' => 'Batang dan akar membusuk', 'category' => 'Batang & Akar'],
            ['code' => 'G15', 'description' => 'Miselium putih seperti kipas di pangkal batang', 'category' => 'Batang & Akar'],
            ['code' => 'G16', 'description' => 'Batang menghitam dan lunak', 'category' => 'Batang & Akar'],

            // Gejala umum tanaman
            ['code' => 'G17', 'description' => 'Tanaman layu keseluruhan', 'category' => 'Umum'],
            ['code' => 'G18', 'description' => 'Pertumbuhan terhambat atau kerdil', 'category' => 'Umum'],
            ['code' => 'G19', 'description' => 'Bercak basah pada kelopak bunga', 'category' => 'Bunga'],
            ['code' => 'G20', 'description' => 'Bintik kuning pada permukaan atas daun', 'category' => 'Daun'],
        ];

        $symptoms = [];
        foreach ($symptomsData as $data) {
            $symptom = Symptom::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
            $symptoms[$data['code']] = $symptom;
        }

        $this->command->info('✅ Symptoms seeded (20 gejala)');

        // ============================================
        // 4. PENYAKIT HYDRANGEA (5 penyakit)
        // ============================================

        // P01 - Bercak Daun Cercospora (Hydrangea)
        $hP01 = Disease::updateOrCreate(
            ['code' => 'HP01'],
            [
                'name' => 'Bercak Daun Cercospora',
                'description' => 'Penyakit jamur yang menyebabkan bercak coklat keunguan pada daun hydrangea, terutama di bagian bawah tanaman. Infeksi berat menyebabkan daun menguning dan rontok.',
                'cause' => 'Jamur Cercospora hydrangeae. Berkembang pada kondisi lembab dan hangat dengan sirkulasi udara buruk.',
                'solution' => 'Buang daun yang terinfeksi. Semprot fungisida berbahan aktif chlorothalonil atau mancozeb setiap 7-14 hari. Pastikan sirkulasi udara baik dengan memangkas tanaman.',
                'prevention' => 'Jaga jarak tanam yang cukup. Siram di pangkal tanaman, hindari membasahi daun. Buang sisa tanaman yang terinfeksi. Semprot fungisida preventif di musim hujan.',
                'plant_id' => $hydrangea->id,
                'is_active' => true,
            ]
        );

        // P02 - Embun Tepung (Hydrangea)
        $hP02 = Disease::updateOrCreate(
            ['code' => 'HP02'],
            [
                'name' => 'Embun Tepung (Powdery Mildew)',
                'description' => 'Penyakit jamur yang membentuk lapisan tepung putih pada permukaan daun hydrangea. Kasus berat menyebabkan daun mengkerut dan bunga menjadi kecil atau cacat.',
                'cause' => 'Jamur Microsphaera penicillata. Berkembang pada siang yang hangat dan malam yang sejuk dengan kelembaban tinggi.',
                'solution' => 'Semprot fungisida berbahan sulfur, neem oil, atau kalium bikarbonat. Pangkas bagian yang terinfeksi berat. Perbaiki sirkulasi udara di sekitar tanaman.',
                'prevention' => 'Tanam di area dengan sirkulasi udara baik. Hindari pemupukan nitrogen berlebihan. Gunakan varietas yang tahan embun tepung.',
                'plant_id' => $hydrangea->id,
                'is_active' => true,
            ]
        );

        // P03 - Busuk Botrytis (Hydrangea)
        $hP03 = Disease::updateOrCreate(
            ['code' => 'HP03'],
            [
                'name' => 'Busuk Botrytis (Botrytis Blight)',
                'description' => 'Penyakit jamur yang menyerang bunga dan daun hydrangea, menyebabkan bunga membusuk dan ditutupi jamur abu-abu berbulu. Sering terjadi pada cuaca lembab dan dingin.',
                'cause' => 'Jamur Botrytis cinerea. Berkembang cepat pada kondisi lembab, dingin, dan kurang sirkulasi udara.',
                'solution' => 'Buang segera bagian tanaman yang terinfeksi. Kurangi kelembaban di sekitar tanaman. Semprot fungisida berbahan aktif thiophanate-methyl. Perbaiki drainase.',
                'prevention' => 'Hindari penyiraman berlebihan. Jaga sirkulasi udara. Buang bunga yang sudah layu. Bersihkan sisa tanaman secara rutin.',
                'plant_id' => $hydrangea->id,
                'is_active' => true,
            ]
        );

        // P04 - Busuk Akar (Hydrangea)
        $hP04 = Disease::updateOrCreate(
            ['code' => 'HP04'],
            [
                'name' => 'Busuk Akar (Root Rot)',
                'description' => 'Penyakit jamur yang menyerang sistem perakaran hydrangea, menyebabkan tanaman layu progressif dan akhirnya mati. Akar dan batang bagian bawah membusuk.',
                'cause' => 'Jamur Armillaria mellea atau Phytophthora. Berkembang pada tanah yang terlalu basah dan drainase buruk.',
                'solution' => 'Perbaiki drainase tanah. Kurangi frekuensi penyiraman. Cabut tanaman yang terinfeksi berat. Aplikasikan fungisida tanah berbahan metalaxyl. Ganti media tanam jika perlu.',
                'prevention' => 'Pastikan drainase tanah baik. Jangan menanam terlalu dalam. Hindari genangan air. Gunakan media tanam yang porus.',
                'plant_id' => $hydrangea->id,
                'is_active' => true,
            ]
        );

        // P05 - Layu Bakteri (Hydrangea)
        $hP05 = Disease::updateOrCreate(
            ['code' => 'HP05'],
            [
                'name' => 'Layu Bakteri (Bacterial Wilt)',
                'description' => 'Penyakit bakteri yang menyebabkan layu cepat pada hydrangea. Dimulai dari satu cabang, lalu menyebar ke seluruh tanaman. Tidak ada pengobatan efektif.',
                'cause' => 'Bakteri Ralstonia solanacearum. Menyebar melalui tanah yang terkontaminasi dan air hujan. Lebih parah pada cuaca panas dan lembab.',
                'solution' => 'Tidak ada pengobatan kimia yang efektif. Cabut dan musnahkan tanaman yang terinfeksi. Jangan tanam hydrangea di lokasi yang sama. Sterilisasi alat pemangkasan.',
                'prevention' => 'Gunakan bibit sehat dan bebas penyakit. Sterilisasi media tanam. Hindari luka pada batang. Jaga kebersihan lingkungan tanam.',
                'plant_id' => $hydrangea->id,
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Hydrangea diseases seeded (5 penyakit)');

        // ============================================
        // 5. PENYAKIT ROSA TINEKE (4 penyakit)
        // ============================================

        // P06 - Bercak Hitam (Rosa Tineke)
        $rP01 = Disease::updateOrCreate(
            ['code' => 'RP01'],
            [
                'name' => 'Bercak Hitam (Black Spot)',
                'description' => 'Penyakit jamur paling umum pada mawar. Menyebabkan bercak hitam bulat pada daun dengan tepi kuning. Daun yang terinfeksi berat akan rontok, melemahkan tanaman.',
                'cause' => 'Jamur Diplocarpon rosae. Berkembang pada kondisi hangat dan lembab, menyebar melalui percikan air hujan.',
                'solution' => 'Buang daun yang terinfeksi. Semprot fungisida berbahan aktif chlorothalonil atau myclobutanil setiap 7-10 hari. Siram di pangkal tanaman. Bersihkan sisa daun yang jatuh.',
                'prevention' => 'Jaga sirkulasi udara yang baik. Siram pagi hari di pangkal tanaman. Gunakan mulsa untuk mencegah percikan tanah. Buang daun jatuh secara rutin.',
                'plant_id' => $rosaTineke->id,
                'is_active' => true,
            ]
        );

        // P07 - Embun Tepung (Rosa Tineke)
        $rP02 = Disease::updateOrCreate(
            ['code' => 'RP02'],
            [
                'name' => 'Embun Tepung Mawar (Powdery Mildew)',
                'description' => 'Penyakit jamur yang membentuk lapisan tepung putih pada daun, tunas, dan kuncup bunga mawar. Menyebabkan daun berkerut dan kuncup gagal mekar.',
                'cause' => 'Jamur Podosphaera pannosa. Berkembang pada siang hangat dan malam sejuk dengan kelembaban tinggi.',
                'solution' => 'Semprot fungisida berbahan sulfur atau neem oil. Pangkas bagian yang terinfeksi. Perbaiki sirkulasi udara. Hindari pemupukan nitrogen berlebihan.',
                'prevention' => 'Tanam di area dengan sinar matahari penuh dan sirkulasi udara baik. Jaga jarak tanam yang cukup. Pilih varietas tahan penyakit.',
                'plant_id' => $rosaTineke->id,
                'is_active' => true,
            ]
        );

        // P08 - Karat Daun (Rosa Tineke)
        $rP03 = Disease::updateOrCreate(
            ['code' => 'RP03'],
            [
                'name' => 'Karat Daun (Rust)',
                'description' => 'Penyakit jamur yang menyebabkan pustula berwarna oranye-kemerahan pada bagian bawah daun mawar. Bagian atas daun menampilkan bintik kuning. Infeksi berat menyebabkan defoliasi.',
                'cause' => 'Jamur Phragmidium sp. Berkembang pada kondisi sejuk dan lembab. Spora menyebar melalui angin.',
                'solution' => 'Buang dan musnahkan daun yang terinfeksi. Semprot fungisida berbahan tembaga (copper-based). Perbaiki sirkulasi udara dengan pemangkasan.',
                'prevention' => 'Jaga sirkulasi udara yang baik. Siram di pangkal tanaman, jangan membasahi daun. Buang sisa tanaman yang terinfeksi di akhir musim.',
                'plant_id' => $rosaTineke->id,
                'is_active' => true,
            ]
        );

        // P09 - Busuk Botrytis (Rosa Tineke)
        $rP04 = Disease::updateOrCreate(
            ['code' => 'RP04'],
            [
                'name' => 'Busuk Botrytis Mawar (Botrytis Blight)',
                'description' => 'Penyakit jamur yang menyerang kuncup dan bunga mawar, menyebabkan bunga membusuk coklat dan ditutupi jamur abu-abu. Sering terjadi pada bunga potong.',
                'cause' => 'Jamur Botrytis cinerea. Berkembang pada kondisi lembab, dingin, dan kurang sirkulasi udara.',
                'solution' => 'Buang bunga dan kuncup yang terinfeksi. Kurangi kelembaban. Perbaiki sirkulasi udara. Semprot fungisida preventif berbahan iprodione.',
                'prevention' => 'Jaga kelembaban rendah. Pangkas untuk sirkulasi udara. Buang kelopak bunga yang jatuh. Hindari penyiraman sore hari.',
                'plant_id' => $rosaTineke->id,
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Rosa Tineke diseases seeded (4 penyakit)');

        // ============================================
        // 6. CF MATRIX (Rule Base - Gejala x Penyakit)
        // ============================================
        // Hubungan gejala-penyakit dengan nilai CF Pakar
        // CF = 0.0-1.0 (tingkat keyakinan pakar bahwa gejala X menunjukkan penyakit Y)

        $cfMatrix = [
            // ===== HYDRANGEA: HP01 - Bercak Daun Cercospora =====
            // Bercak coklat pada daun → sangat khas (CF tinggi)
            ['disease' => $hP01, 'symptom' => $symptoms['G01'], 'cf' => 0.8],
            // Daun menguning → sering terjadi (CF sedang)
            ['disease' => $hP01, 'symptom' => $symptoms['G02'], 'cf' => 0.6],
            // Daun rontok prematur → akibat lanjutan (CF sedang)
            ['disease' => $hP01, 'symptom' => $symptoms['G03'], 'cf' => 0.6],

            // ===== HYDRANGEA: HP02 - Embun Tepung =====
            // Lapisan tepung putih → gejala paling khas (CF sangat tinggi)
            ['disease' => $hP02, 'symptom' => $symptoms['G05'], 'cf' => 1.0],
            // Daun mengkerut → sering terjadi bersamaan (CF tinggi)
            ['disease' => $hP02, 'symptom' => $symptoms['G06'], 'cf' => 0.8],
            // Kuncup gagal mekar → pada kasus berat (CF sedang)
            ['disease' => $hP02, 'symptom' => $symptoms['G11'], 'cf' => 0.6],
            // Pertumbuhan terhambat → efek lanjutan (CF sedang)
            ['disease' => $hP02, 'symptom' => $symptoms['G18'], 'cf' => 0.6],

            // ===== HYDRANGEA: HP03 - Busuk Botrytis =====
            // Bunga membusuk coklat → gejala utama (CF sangat tinggi)
            ['disease' => $hP03, 'symptom' => $symptoms['G10'], 'cf' => 0.8],
            // Jamur abu-abu berbulu pada bunga → gejala paling khas (CF sangat tinggi)
            ['disease' => $hP03, 'symptom' => $symptoms['G12'], 'cf' => 1.0],
            // Bercak basah pada kelopak → gejala awal (CF tinggi)
            ['disease' => $hP03, 'symptom' => $symptoms['G19'], 'cf' => 0.8],
            // Mahkota bunga rontok → efek lanjutan (CF sedang)
            ['disease' => $hP03, 'symptom' => $symptoms['G13'], 'cf' => 0.6],

            // ===== HYDRANGEA: HP04 - Busuk Akar =====
            // Daun layu meski sudah disiram → gejala paling khas (CF sangat tinggi)
            ['disease' => $hP04, 'symptom' => $symptoms['G07'], 'cf' => 1.0],
            // Batang dan akar membusuk → gejala utama (CF sangat tinggi)
            ['disease' => $hP04, 'symptom' => $symptoms['G14'], 'cf' => 0.8],
            // Miselium putih di pangkal batang → gejala khas (CF tinggi)
            ['disease' => $hP04, 'symptom' => $symptoms['G15'], 'cf' => 0.8],
            // Tanaman layu keseluruhan → gejala umum (CF sedang)
            ['disease' => $hP04, 'symptom' => $symptoms['G17'], 'cf' => 0.6],

            // ===== HYDRANGEA: HP05 - Layu Bakteri =====
            // Tanaman layu keseluruhan → gejala paling khas (CF sangat tinggi)
            ['disease' => $hP05, 'symptom' => $symptoms['G17'], 'cf' => 1.0],
            // Daun layu meski disiram → pembeda dengan kekeringan (CF tinggi)
            ['disease' => $hP05, 'symptom' => $symptoms['G07'], 'cf' => 0.8],
            // Batang menghitam dan lunak → gejala lanjutan (CF tinggi)
            ['disease' => $hP05, 'symptom' => $symptoms['G16'], 'cf' => 0.8],
            // Pertumbuhan terhambat → efek umum (CF sedang)
            ['disease' => $hP05, 'symptom' => $symptoms['G18'], 'cf' => 0.6],

            // ===== ROSA TINEKE: RP01 - Bercak Hitam =====
            // Bercak hitam bulat dengan tepi kuning → gejala paling khas (CF sangat tinggi)
            ['disease' => $rP01, 'symptom' => $symptoms['G04'], 'cf' => 1.0],
            // Daun menguning → akibat infeksi (CF tinggi)
            ['disease' => $rP01, 'symptom' => $symptoms['G02'], 'cf' => 0.8],
            // Daun rontok prematur → efek lanjutan (CF tinggi)
            ['disease' => $rP01, 'symptom' => $symptoms['G03'], 'cf' => 0.8],
            // Bercak coklat pada daun → bisa bersama bercak hitam (CF sedang)
            ['disease' => $rP01, 'symptom' => $symptoms['G01'], 'cf' => 0.6],

            // ===== ROSA TINEKE: RP02 - Embun Tepung Mawar =====
            // Lapisan tepung putih → gejala paling khas (CF sangat tinggi)
            ['disease' => $rP02, 'symptom' => $symptoms['G05'], 'cf' => 1.0],
            // Daun mengkerut → efek jamur (CF tinggi)
            ['disease' => $rP02, 'symptom' => $symptoms['G06'], 'cf' => 0.8],
            // Kuncup gagal mekar → pada kasus berat (CF tinggi)
            ['disease' => $rP02, 'symptom' => $symptoms['G11'], 'cf' => 0.8],
            // Daun menguning → kadang terjadi (CF sedang)
            ['disease' => $rP02, 'symptom' => $symptoms['G02'], 'cf' => 0.6],

            // ===== ROSA TINEKE: RP03 - Karat Daun =====
            // Bintik karat oranye pada bawah daun → gejala paling khas (CF sangat tinggi)
            ['disease' => $rP03, 'symptom' => $symptoms['G08'], 'cf' => 1.0],
            // Bintik kuning pada permukaan atas daun → gejala bersamaan (CF tinggi)
            ['disease' => $rP03, 'symptom' => $symptoms['G20'], 'cf' => 0.8],
            // Daun rontok prematur → efek lanjutan (CF sedang)
            ['disease' => $rP03, 'symptom' => $symptoms['G03'], 'cf' => 0.6],
            // Daun menguning → efek umum (CF sedang)
            ['disease' => $rP03, 'symptom' => $symptoms['G02'], 'cf' => 0.6],

            // ===== ROSA TINEKE: RP04 - Busuk Botrytis Mawar =====
            // Bunga membusuk coklat → gejala utama (CF sangat tinggi)
            ['disease' => $rP04, 'symptom' => $symptoms['G10'], 'cf' => 0.8],
            // Jamur abu-abu berbulu → gejala paling khas (CF sangat tinggi)
            ['disease' => $rP04, 'symptom' => $symptoms['G12'], 'cf' => 1.0],
            // Kuncup gagal mekar → gejala bersamaan (CF tinggi)
            ['disease' => $rP04, 'symptom' => $symptoms['G11'], 'cf' => 0.8],
            // Bercak basah pada kelopak → gejala awal (CF tinggi)
            ['disease' => $rP04, 'symptom' => $symptoms['G19'], 'cf' => 0.8],
        ];

        foreach ($cfMatrix as $rule) {
            DB::table('disease_symptoms')->updateOrInsert(
                [
                    'disease_id' => $rule['disease']->id,
                    'symptom_id' => $rule['symptom']->id,
                ],
                [
                    'certainty_factor' => $rule['cf'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✅ CF Matrix seeded (36 aturan gejala-penyakit)');
        $this->command->info('');
        $this->command->info('📊 Ringkasan Data:');
        $this->command->info('   - 2 Tanaman: Bigleaf Hydrangea, Rosa Tineke');
        $this->command->info('   - 9 Penyakit: 5 Hydrangea + 4 Rosa Tineke');
        $this->command->info('   - 20 Gejala: Daun, Bunga, Batang & Akar, Umum');
        $this->command->info('   - 36 Aturan CF (rule base)');
        $this->command->info('   - 5 Level CF User');
        $this->command->info('');
        $this->command->info('🎯 Siap untuk testing diagnosis!');
    }
}
