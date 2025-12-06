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
            ['name' => 'Anggota', 'code' => 'ANGG'],
        ];
        foreach ($defaults as $d) {
            UnionPosition::firstOrCreate(['name' => $d['name']], ['code' => $d['code']]);
        }
    }
}

