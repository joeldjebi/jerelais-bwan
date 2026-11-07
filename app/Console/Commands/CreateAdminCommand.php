<?php

namespace App\Console\Commands;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

#[Signature('jerelais:create-admin {email : Adresse e-mail de connexion} {--name= : Nom affiché}')]
#[Description('Crée le premier compte administrateur de la console (mot de passe saisi de façon masquée)')]
class CreateAdminCommand extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $email = Str::lower(is_string($this->argument('email')) ? $this->argument('email') : '');
        $password = $this->secret('Mot de passe (10 caractères minimum)');
        $password = is_string($password) ? $password : '';

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', Password::min(10)]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $this->option('name') ?: Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::Admin->value);

        $audit->record('users.admin_created', $user, ['email' => $email]);

        $this->components->info("Administrateur créé : {$email}. La double authentification sera demandée à la première connexion.");

        return self::SUCCESS;
    }
}
