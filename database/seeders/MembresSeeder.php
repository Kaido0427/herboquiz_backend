<?php

namespace Database\Seeders;

use App\Models\Membre;
use Illuminate\Database\Seeder;

/**
 * Les six administrateurs du groupe, poses au depart.
 *
 * Chacun pourra corriger son propre nom depuis l'administration : ce sont des
 * pseudonymes de groupe, ils changent. On ne stocke aucun numero de telephone
 * ici — la liste ne sert qu'a identifier qui attribue les points.
 */
class MembresSeeder extends Seeder
{
    public function run(): void
    {
        $admins = ['Kaido', 'Eckson Leroy', 'Benoit', 'Titus', 'Kevin HOUETO', 'Eagle Owl'];

        foreach ($admins as $i => $nom) {
            Membre::firstOrCreate(
                ['nom' => $nom, 'role' => 'admin'],
                ['ordre' => $i],
            );
        }
    }
}
