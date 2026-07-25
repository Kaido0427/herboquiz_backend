<?php

namespace App\Support;

use App\Models\Manche;
use Illuminate\Support\Collection;

/**
 * La repartition des equipes dans les poules, ecrite UNE seule fois.
 *
 * En serpentin (une equipe par poule a tour de role) pour ne pas concentrer un
 * meme groupe d'amis dans la meme poule. `sync` remplace le tirage precedent :
 * marche que les poules soient vides ou deja remplies. La manche de chaque poule
 * suit la meme composition.
 *
 * Utilise par : appliquer un format, re-tirer, et reconstituer les equipes —
 * cette derniere laissait sinon les poules vides et l'alerte « equipes non
 * rattachees » revenait a chaque fois.
 */
class Tirage
{
    public static function repartir(Collection $poules, Collection $equipes): void
    {
        if ($poules->isEmpty()) {
            return;
        }

        $parPoule = [];
        $equipes->shuffle()->values()->each(function ($e, $i) use ($poules, &$parPoule) {
            $parPoule[$poules[$i % $poules->count()]->id][] = $e->id;
        });

        foreach ($poules as $p) {
            $ids = $parPoule[$p->id] ?? [];
            $p->equipes()->sync($ids);
            Manche::where('poule_id', $p->id)->get()
                ->each(fn ($m) => $m->equipes()->sync($ids));
        }
    }
}
