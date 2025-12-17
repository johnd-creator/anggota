<?php

namespace Database\Seeders;

use App\Models\LetterCategory;
use Illuminate\Database\Seeder;

class LetterCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'ORG', 'name' => 'Organisasi', 'description' => 'Surat terkait organisasi internal', 'color' => 'green', 'sort_order' => 1],
            ['code' => 'AGT', 'name' => 'Keanggotaan', 'description' => 'Surat terkait keanggotaan', 'color' => 'cyan', 'sort_order' => 2],
            ['code' => 'HI', 'name' => 'Hubungan Industrial', 'description' => 'Surat hubungan industrial dengan perusahaan', 'color' => 'indigo', 'sort_order' => 3],
            ['code' => 'ADV', 'name' => 'Advokasi', 'description' => 'Surat advokasi dan pendampingan anggota', 'color' => 'red', 'sort_order' => 4],
            ['code' => 'EKS', 'name' => 'Eksternal', 'description' => 'Surat ke pihak eksternal', 'color' => 'amber', 'sort_order' => 5],
            ['code' => 'UND', 'name' => 'Undangan', 'description' => 'Surat undangan untuk rapat, acara, dan kegiatan', 'color' => 'blue', 'sort_order' => 6],
            ['code' => 'SK', 'name' => 'Surat Keputusan', 'description' => 'Surat keputusan resmi organisasi', 'color' => 'purple', 'sort_order' => 7],
            ['code' => 'PEM', 'name' => 'Pemberitahuan', 'description' => 'Surat pemberitahuan umum', 'color' => 'neutral', 'sort_order' => 8],
            ['code' => 'REK', 'name' => 'Rekomendasi', 'description' => 'Surat rekomendasi untuk anggota', 'color' => 'teal', 'sort_order' => 9],
        ];

        foreach ($categories as $category) {
            LetterCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'color' => $category['color'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
