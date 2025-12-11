<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OrganizationUnitSeeder::class,
            UnionPositionSeeder::class,
            AspirationCategorySeeder::class,
            DefaultFinanceCategorySeeder::class,
            UserSeeder::class,
            MemberSeeder::class,
            NotificationPreferenceSeeder::class,
        ]);
    }
}
