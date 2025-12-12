<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UnionPosition;

class UnionPositionSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Ketua', 'code' => 'KETUA'],
            ['name' => 'Sekretaris', 'code' => 'SEKRE'],
            ['name' => 'Bendahara', 'code' => 'BENDA'],
            ['name' => 'Wakil Ketua', 'code' => 'WAKET'],
            ['name' => 'Wakil Sekretaris', 'code' => 'WASEK'],
            ['name' => 'Wakil Bendahara', 'code' => 'WABEN'],
            ['name' => 'Divisi Hukum & Advokasi', 'code' => 'HUKUM'],
            ['name' => 'Divisi Pendidikan & Pelatihan', 'code' => 'DIKLAT'],
            ['name' => 'Divisi Kesejahteraan', 'code' => 'KESEH'],
            ['name' => 'Divisi Organisasi & Keanggotaan', 'code' => 'ORGAN'],
            ['name' => 'Divisi Komunikasi & Publikasi', 'code' => 'KOMPU'],
            ['name' => 'Pengawas', 'code' => 'PENGA'],
            ['name' => 'Anggota', 'code' => 'ANGG'],
        ];
        foreach ($defaults as $d) {
            UnionPosition::firstOrCreate(['name' => $d['name']], ['code' => $d['code']]);
        }
    }
}
