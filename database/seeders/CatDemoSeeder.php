<?php

namespace Database\Seeders;

use App\Models\Cat;
use App\Models\CatPhoto;
use App\Models\Shelter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 5 cat profiles with varying levels of data completeness
     */
    public function run(): void
    {
        // Get existing shelters
        $shelter1 = Shelter::first();
        $shelter2 = Shelter::latest()->first() ?? $shelter1;

        if (!$shelter1) {
            $this->command->error('❌ Tidak ada shelter! Jalankan DatabaseSeeder dulu.');
            return;
        }

        $this->command->info('🐱 Membuat 5 profil anabul demo...');

        // =====================================================
        // CAT 1: SUPER LENGKAP - Kucing ras dengan semua data
        // =====================================================
        $cat1 = Cat::create([
            'shelter_id' => $shelter1->id,
            'name' => 'Princess Bella',
            'breed' => 'Persian',
            'date_of_birth' => now()->subMonths(18),
            'age_months' => 18,
            'age_category' => 'adult',
            'gender' => 'Betina',
            'color' => 'Putih',
            'weight' => 4.2,
            'description' => "Hai, namaku Princess Bella! 👑\n\nAku kucing Persian asli dengan bulu putih salju yang lembut. Aku suka tidur di kasur empuk dan makan Royal Canin. Aku sudah divaksin lengkap (F3 + Rabies) dan steril.\n\nAku ramah sama anak kecil tapi agak pemalu sama kucing lain. Kalau kamu cari teman yang kalem dan elegan, aku orangnya! 💕\n\nCatatan: Aku butuh grooming rutin 2x seminggu ya karena buluku panjang.",
            'energy_level' => 'low',
            'temperament' => 'shy',
            'good_with_kids' => true,
            'good_with_cats' => false,
            'good_with_dogs' => false,
            'indoor_only' => true,
            'tags' => json_encode(['Kalem', 'Manja', 'Bulu Panjang', 'Ras Murni', 'Cocok WFH']),
            'health_status' => 'Sangat Sehat',
            'vaccination_status' => 'complete',
            'is_sterilized' => true,
            'is_dewormed' => true,
            'is_flea_free' => true,
            'special_condition' => null,
            'medical_notes' => 'Vaksin F3 tanggal 15 Nov 2024, Rabies 20 Nov 2024. Steril 1 Des 2024. Tidak ada alergi.',
            'youtube_url' => 'https://www.youtube.com/watch?v=hY7m5jjJ9mw',
            'adoption_fee' => 850000,
            'status' => 'available',
            'is_urgent' => false,
            'adoption_requirements' => 'Harus indoor, punya waktu untuk grooming, tidak ada anjing di rumah.',
            'body_type' => 'Cobby',
            'coat_length' => 'Panjang',
            'coat_pattern' => 'Solid',
            'face_shape' => 'Pesek',
            'eye_color' => 'Biru',
            'tail_type' => 'Fluffy',
            'leg_type' => 'Pendek',
            'ear_type' => 'Normal',
            'nose_type' => 'Pesek',
        ]);

        // Multiple photos for complete cat
        $photos1 = [
            'https://images.unsplash.com/photo-1513360371669-4adf3dd7dff8?w=800',
            'https://images.unsplash.com/photo-1596854407944-bf87f6fdd49e?w=800',
            'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=800',
            'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=800',
        ];
        foreach ($photos1 as $i => $url) {
            CatPhoto::create([
                'cat_id' => $cat1->id,
                'photo_path' => $url,
                'is_primary' => $i === 0,
            ]);
        }

        $this->command->info('✅ 1. Princess Bella (LENGKAP BANGET) - Persian dewasa dengan semua data');

        // =====================================================
        // CAT 2: LENGKAP TAPI URGENT - Kucing butuh adopsi cepat
        // =====================================================
        $cat2 = Cat::create([
            'shelter_id' => $shelter2->id,
            'name' => 'Rusty',
            'breed' => 'Domestik',
            'date_of_birth' => now()->subMonths(8),
            'age_months' => 8,
            'age_category' => 'kitten',
            'gender' => 'Jantan',
            'color' => 'Orange Tabby',
            'weight' => 2.8,
            'description' => "🚨 URGENT: Shelter hampir penuh!\n\nHalo, aku Rusty si Oyen! Aku diselamatkan dari jalanan bulan lalu. Awalnya aku takut sama manusia, tapi sekarang aku sudah jinak dan suka digendong.\n\nAku punya energi tinggi dan suka main kejar-kejaran. Cocok buat kamu yang aktif dan punya waktu main bareng aku! 🐾",
            'energy_level' => 'high',
            'temperament' => 'friendly',
            'good_with_kids' => true,
            'good_with_cats' => true,
            'good_with_dogs' => true,
            'indoor_only' => false,
            'tags' => json_encode(['Oyen', 'Aktif', 'Ex-Jalanan', 'Jinak', 'Survivor']),
            'health_status' => 'Sehat',
            'vaccination_status' => 'partial',
            'is_sterilized' => false,
            'is_dewormed' => true,
            'is_flea_free' => true,
            'special_condition' => null,
            'medical_notes' => 'Sudah vaksin F3 dosis 1. Dosis 2 dijadwalkan Januari 2026.',
            'youtube_url' => 'https://www.youtube.com/watch?v=CBMet3yNfqk',
            'adoption_fee' => 100000,
            'status' => 'available',
            'is_urgent' => true,
            'adoption_requirements' => 'Tidak ada syarat khusus, yang penting sayang kucing!',
            'body_type' => 'Normal',
            'coat_length' => 'Pendek',
            'coat_pattern' => 'Tabby',
            'face_shape' => 'Normal',
            'eye_color' => 'Kuning',
        ]);

        CatPhoto::create([
            'cat_id' => $cat2->id,
            'photo_path' => 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?w=800',
            'is_primary' => true,
        ]);
        CatPhoto::create([
            'cat_id' => $cat2->id,
            'photo_path' => 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=800',
            'is_primary' => false,
        ]);

        $this->command->info('✅ 2. Rusty (URGENT + LENGKAP) - Oyen kitten ex-jalanan');

        // =====================================================
        // CAT 3: SEDANG - Data cukup tapi tidak lengkap 100%
        // =====================================================
        $cat3 = Cat::create([
            'shelter_id' => $shelter1->id,
            'name' => 'Milo',
            'breed' => 'British Shorthair Mix',
            'age_months' => 24,
            'age_category' => 'adult',
            'gender' => 'Jantan',
            'color' => 'Abu-abu',
            'weight' => 5.0,
            'description' => "Milo adalah kucing kalem yang suka rebahan. Cocok untuk apartemen dan pekerja kantoran. Sudah vaksin dan jinak.",
            'energy_level' => 'low',
            'temperament' => 'independent',
            'good_with_kids' => true,
            'good_with_cats' => true,
            'tags' => json_encode(['Kalem', 'Apartemen', 'Jinak']),
            'health_status' => 'Sehat',
            'vaccination_status' => 'complete',
            'is_sterilized' => true,
            'is_dewormed' => true,
            'adoption_fee' => 350000,
            'status' => 'available',
        ]);

        CatPhoto::create([
            'cat_id' => $cat3->id,
            'photo_path' => 'https://images.unsplash.com/photo-1494256997604-768d1f608cdc?w=800',
            'is_primary' => true,
        ]);

        $this->command->info('✅ 3. Milo (DATA SEDANG) - British mix, info sebagian');

        // =====================================================
        // CAT 4: MINIMAL - Data sangat basic
        // =====================================================
        $cat4 = Cat::create([
            'shelter_id' => $shelter2->id,
            'name' => 'Hitam',
            'breed' => 'Domestik',
            'age_months' => 12,
            'age_category' => 'adult',
            'gender' => 'Jantan',
            'color' => 'Hitam',
            'description' => "Kucing hitam ramah, butuh rumah baru.",
            'health_status' => 'Sehat',
            'vaccination_status' => 'none',
            'adoption_fee' => 0,
            'status' => 'available',
        ]);

        CatPhoto::create([
            'cat_id' => $cat4->id,
            'photo_path' => 'https://images.unsplash.com/photo-1516733725897-1aa73b87c8e8?w=800',
            'is_primary' => true,
        ]);

        $this->command->info('✅ 4. Hitam (DATA MINIMAL) - Kucing domestik, info seadanya');

        // =====================================================
        // CAT 5: MEDIUM + SPECIAL NEEDS - Ada kondisi khusus
        // =====================================================
        $cat5 = Cat::create([
            'shelter_id' => $shelter1->id,
            'name' => 'Lucky',
            'breed' => 'Domestik',
            'date_of_birth' => now()->subMonths(36),
            'age_months' => 36,
            'age_category' => 'adult',
            'gender' => 'Betina',
            'color' => 'Calico',
            'weight' => 3.5,
            'description' => "Lucky adalah kucing penyintas yang kehilangan satu kaki depannya karena kecelakaan. Tapi dia tetap ceria dan aktif! 💪\n\nDia butuh adopter yang sabar dan pengertian. Lucky sudah beradaptasi dengan baik dan bisa melakukan aktivitas normal.\n\nNama Lucky karena dia beruntung bisa selamat dan sekarang mencari rumah forever! 🏠",
            'energy_level' => 'medium',
            'temperament' => 'friendly',
            'good_with_kids' => true,
            'good_with_cats' => true,
            'good_with_dogs' => false,
            'indoor_only' => true,
            'tags' => json_encode(['Special Needs', 'Survivor', 'Calico', 'Penyayang']),
            'health_status' => 'Special Needs',
            'vaccination_status' => 'complete',
            'is_sterilized' => true,
            'is_dewormed' => true,
            'is_flea_free' => true,
            'special_condition' => 'Amputasi kaki depan kiri - sudah beradaptasi dengan baik',
            'medical_notes' => 'Operasi amputasi sukses Juni 2024. Checkup rutin tiap 3 bulan. Tidak perlu perawatan khusus.',
            'adoption_fee' => 0,
            'status' => 'available',
            'is_urgent' => false,
            'adoption_requirements' => 'Harus indoor, adopter yang sabar, tidak ada anjing.',
            'body_type' => 'Normal',
            'coat_length' => 'Pendek',
            'coat_pattern' => 'Calico',
            'eye_color' => 'Hijau',
        ]);

        CatPhoto::create([
            'cat_id' => $cat5->id,
            'photo_path' => 'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=800',
            'is_primary' => true,
        ]);
        CatPhoto::create([
            'cat_id' => $cat5->id,
            'photo_path' => 'https://images.unsplash.com/photo-1526336024174-e58f5cdd8e13?w=800',
            'is_primary' => false,
        ]);

        $this->command->info('✅ 5. Lucky (SPECIAL NEEDS) - Kucing 3 kaki yang tetap ceria');

        $this->command->info('');
        $this->command->info('🎉 5 profil anabul demo berhasil dibuat!');
        $this->command->info('   - 1 kucing SUPER LENGKAP (Princess Bella)');
        $this->command->info('   - 1 kucing URGENT + LENGKAP (Rusty)');
        $this->command->info('   - 1 kucing DATA SEDANG (Milo)');
        $this->command->info('   - 1 kucing DATA MINIMAL (Hitam)');
        $this->command->info('   - 1 kucing SPECIAL NEEDS (Lucky)');
    }
}
