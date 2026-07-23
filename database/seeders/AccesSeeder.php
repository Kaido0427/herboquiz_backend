<?php

namespace Database\Seeders;

use App\Models\Acces;
use Illuminate\Database\Seeder;

/**
 * Un code par role, cree une seule fois.
 *
 * Il n'y a volontairement PAS de compte par personne : les six administrateurs
 * partagent un meme code, et les animateurs un autre. L'identite est saisie a
 * la connexion, ce qui suffit a tracer qui attribue les points sans imposer une
 * gestion de comptes a un evenement qui dure une semaine.
 */
class AccesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'modo'] as $role) {
            if (Acces::where('role', $role)->exists()) {
                continue;   // ne jamais ecraser un code deja distribue
            }

            $acces = new Acces(['role' => $role]);
            $acces->definirCode(Acces::genererCode());

            $this->command?->info("Code {$role} : {$acces->code_clair}");
        }
    }
}
