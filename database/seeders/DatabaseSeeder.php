<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Shelter;
use App\Models\Cat;
use App\Models\CatPhoto;
use App\Models\Article;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin MangOyen',
            'email' => 'admin@mangoyen.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create shelter users
        $shelterUser1 = User::create([
            'name' => 'Rumah Kucing Jakarta',
            'email' => 'shelter1@mangoyen.com',
            'phone' => '08123456789',
            'password' => Hash::make('password'),
            'role' => 'shelter',
            'email_verified_at' => now(),
        ]);

        $shelterUser2 = User::create([
            'name' => 'Bandung Cat Rescue',
            'email' => 'shelter2@mangoyen.com',
            'phone' => '08567891234',
            'password' => Hash::make('password'),
            'role' => 'shelter',
            'email_verified_at' => now(),
        ]);

        // Create adopter user
        $adopter = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => Hash::make('password'),
            'role' => 'adopter',
            'email_verified_at' => now(),
        ]);

        // Create shelters
        $shelter1 = Shelter::create([
            'user_id' => $shelterUser1->id,
            'name' => 'Rumah Kucing Jakarta',
            'slug' => 'rumah-kucing-jakarta',
            'description' => 'Shelter kucing terbesar di Jakarta dengan ratusan kucing lucu menunggu majikan baru.',
            'address' => 'Jl. Kucing Manis No. 123',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'is_verified' => true,
            'rating' => 4.8,
            'total_adopted' => 250,
        ]);

        $shelter2 = Shelter::create([
            'user_id' => $shelterUser2->id,
            'name' => 'Bandung Cat Rescue',
            'slug' => 'bandung-cat-rescue',
            'description' => 'Misi kami: menyelamatkan kucing terlantar dan mencarikan rumah yang penuh cinta.',
            'address' => 'Jl. Dago No. 45',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'is_verified' => true,
            'rating' => 4.9,
            'total_adopted' => 180,
        ]);

        // Create cats
        $catsData = [
            ['name' => 'Mochi', 'breed' => 'Persia', 'age_category' => 'adult', 'age_months' => 24, 'gender' => 'jantan', 'color' => 'Putih', 'adoption_fee' => 500000, 'shelter_id' => $shelter1->id, 'photo' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Simba', 'breed' => 'Domestik', 'age_category' => 'kitten', 'age_months' => 6, 'gender' => 'jantan', 'color' => 'Oranye', 'adoption_fee' => 150000, 'shelter_id' => $shelter2->id, 'photo' => 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Luna', 'breed' => 'British Shorthair', 'age_category' => 'adult', 'age_months' => 18, 'gender' => 'betina', 'color' => 'Abu-abu', 'adoption_fee' => 750000, 'shelter_id' => $shelter2->id, 'photo' => 'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Oyen', 'breed' => 'Munchkin', 'age_category' => 'kitten', 'age_months' => 4, 'gender' => 'jantan', 'color' => 'Oranye', 'adoption_fee' => 600000, 'shelter_id' => $shelter1->id, 'photo' => 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Boba', 'breed' => 'Persia', 'age_category' => 'adult', 'age_months' => 36, 'gender' => 'betina', 'color' => 'Hitam Putih', 'adoption_fee' => 450000, 'shelter_id' => $shelter1->id, 'photo' => 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Chiki', 'breed' => 'Scottish Fold', 'age_category' => 'kitten', 'age_months' => 5, 'gender' => 'betina', 'color' => 'Coklat', 'adoption_fee' => 800000, 'shelter_id' => $shelter2->id, 'photo' => 'https://images.unsplash.com/photo-1511044568932-338cba0fb803?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Kopi', 'breed' => 'Domestik', 'age_category' => 'adult', 'age_months' => 30, 'gender' => 'jantan', 'color' => 'Hitam', 'adoption_fee' => 100000, 'shelter_id' => $shelter1->id, 'photo' => 'https://images.unsplash.com/photo-1516733725897-1aa73b87c8e8?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Snow', 'breed' => 'Anggora', 'age_category' => 'adult', 'age_months' => 24, 'gender' => 'betina', 'color' => 'Putih', 'adoption_fee' => 700000, 'shelter_id' => $shelter2->id, 'photo' => 'https://images.unsplash.com/photo-1494256997604-768d1f608cdc?auto=format&fit=crop&w=600&q=80'],
        ];

        foreach ($catsData as $catData) {
            $photo = $catData['photo'];
            unset($catData['photo']);

            $cat = Cat::create([
                ...$catData,
                'slug' => Str::slug($catData['name']) . '-' . Str::random(6),
                'description' => 'Kucing yang sangat lucu dan menggemaskan. Sudah vaksin lengkap dan sehat.',
                'health_status' => 'Sehat',
                'vaccination_status' => 'Lengkap',
                'is_sterilized' => rand(0, 1),
                'status' => 'available',
            ]);

            CatPhoto::create([
                'cat_id' => $cat->id,
                'photo_path' => $photo,
                'is_primary' => true,
            ]);
        }

        // Create articles
        $articles = [
            [
                'title' => 'Starter Pack Buat Kamu yang Baru Pertama Pelihara Kucing',
                'slug' => 'starter-pack-pelihara-kucing',
                'excerpt' => 'List barang wajib punya: Litterbox, pasir wangi, sama sediaan mental yang kuat!',
                'content' => 'Mau jadi babu kucing yang baik? Simak artikel lengkapnya di sini...',
                'category' => 'HOOMAN GUIDE',
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'title' => 'Kenapa Kucing Suka Nemplok di Laptop? Ini Penjelasan Ilmiahnya',
                'slug' => 'kucing-nemplok-laptop',
                'excerpt' => 'Ternyata bukan cuma minta perhatian, ada alasan saintifik lho!',
                'content' => 'Pernah kerja terus laptopnya ditempelin kucing? Ternyata...',
                'category' => 'FUN FACT',
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'title' => 'Cara Bikin Kucing Betah di Rumah Baru',
                'slug' => 'kucing-betah-rumah-baru',
                'excerpt' => 'Tips adaptasi kucing adopsi biar ga stres dan cepet akrab.',
                'content' => 'Setelah adopsi, kucing butuh waktu untuk adaptasi...',
                'category' => 'ADOPTION TIPS',
                'is_published' => true,
                'published_at' => now(),
            ],
        ];

        foreach ($articles as $article) {
            Article::create($article);
        }

        echo "✅ Seeder berhasil! Data sample sudah ditambahkan.\n";
    }
}
