<?php

namespace App\Console\Commands;

use App\Http\Requests\Staff\StoreStaffRequest;
use App\Models\Profile;
use App\Services\StaffAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateStaff extends Command
{
    protected $signature = 'staff:create
        {email : E-mail professionnel}
        {name : Nom complet}
        {phone : Téléphone au format international}
        {organization : Identifiant (UUID) de l\'organisme}
        {profile : Code du profil}
        {--role=supervisor : supervisor, team_lead ou manager}
        {--territory= : Identifiant (UUID) du territoire}';

    protected $description = 'Crée un compte agent et envoie son invitation (amorçage du premier superviseur)';

    public function handle(StaffAccountService $accounts): int
    {
        $profile = Profile::query()->where('code', (string) $this->argument('profile'))->first();

        if ($profile === null) {
            $this->error('Profil introuvable.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'name' => (string) $this->argument('name'),
            'email' => Str::lower(trim((string) $this->argument('email'))),
            'phone' => (string) $this->argument('phone'),
            'role' => (string) $this->option('role'),
            'profile_id' => $profile->id,
            'organization_id' => (string) $this->argument('organization'),
            'territory_id' => $this->option('territory') ?: null,
        ], (new StoreStaffRequest)->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $accounts->create($validator->validated());

        $this->info('Compte créé. Invitation envoyée.');

        return self::SUCCESS;
    }
}
