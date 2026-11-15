<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            'citizen' => 'Usager',
            'municipal_police' => 'Police municipale',
            'municipal_technical_agent' => 'Agent technique municipal',
            'network_agent' => 'Agent de réseau',
            'law_enforcement' => 'Forces de l\'ordre',
            'urbanism_inspector' => 'Inspecteur d\'urbanisme',
        ];

        foreach ($profiles as $code => $name) {
            Profile::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
