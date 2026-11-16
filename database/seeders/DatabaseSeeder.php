<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(ProfileSeeder::class);

        if (! app()->environment('local') || User::query()->exists()) {
            return;
        }

        $agentProfile = Profile::where('code', 'municipal_technical_agent')->firstOrFail();
        $citizenProfile = Profile::where('code', 'citizen')->firstOrFail();

        foreach (UserRole::cases() as $role) {
            User::factory()->staff($role)->for($agentProfile)->create([
                'name' => 'Démo '.$role->value,
                'email' => $role->value.'@oep.test',
            ]);
        }

        User::factory()->for($citizenProfile)->create([
            'name' => 'Usager démo',
            'phone' => '+2250000000000',
        ]);
    }
}
