<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\NotificationPreference;

class NotificationPreferenceSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = ['inapp' => true, 'email' => true, 'wa' => false];
        foreach (User::all(['id']) as $user) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id],
                ['channels' => $defaults, 'digest_daily' => false]
            );
        }
    }
}

