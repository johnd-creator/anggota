<?php

namespace Database\Seeders;

use App\Models\AspirationCategory;
use Illuminate\Database\Seeder;

class AspirationCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Kesejahteraan', 'description' => 'Aspirasi terkait kesejahteraan anggota'],
            ['name' => 'Organisasi', 'description' => 'Aspirasi terkait struktur dan kebijakan organisasi'],
            ['name' => 'Kegiatan', 'description' => 'Usulan kegiatan atau acara'],
            ['name' => 'Fasilitas', 'description' => 'Aspirasi terkait fasilitas dan sarana prasarana'],
            ['name' => 'Pendidikan & Pelatihan', 'description' => 'Usulan program pendidikan atau pelatihan'],
            ['name' => 'Lainnya', 'description' => 'Aspirasi umum lainnya'],
        ];

        foreach ($categories as $category) {
            AspirationCategory::firstOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }
    }
}
