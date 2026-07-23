<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Models\Manche;
use App\Models\Participant;
use App\Models\Poule;
use App\Models\Reglage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Simulation du format selon le nombre d'inscrits.
 *
 * Le format ne peut pas etre decide avant la cloture des inscriptions : il
 * depend du nombre de joueurs. Ce module PROPOSE un format complet a partir de
 * l'effectif, et l'administrateur reste libre de tout modifier avant de valider.
 *
 * Aucun seuil n'est ecrit en dur ici : ils viennent tous des reglages, donc ils
 * se changent depuis l'administration sans repasser par le code.
 */
class SimulationController extends Controller
{
    public function simuler(Request $request)
    {
        $data = $request->validate([
            'effectif' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'mode'     => ['nullable', 'in:solo,duo,auto'],
        ]);

        $effectif = $data['effectif'] ?? Participant::where('confirme', true)->count();
        $mode     = $data['mode'] ?? 'auto';

        return response()->json($this->calculer($effectif, $mode));
    }

    private function calculer(int $effectif, string $mode): array
    {
        $maxParPoule    = (int) Reglage::valeur('simulation.max_par_poule', 20);
        $qualifies      = (int) Reglage::valeur('simulation.qualifies_par_poule', 4);
        $qParJoueur     = (float) Reglage::valeur('simulation.questions_par_joueur', 1);
        $qMin           = (int) Reglage::valeur('simulation.questions_min', 12);
        $qMax           = (int) Reglage::valeur('simulation.questions_max', 20);
        $seuilSansPoule = (int) Reglage::valeur('simulation.seuil_sans_poules', 16);
        $seuilDuo       = (int) Reglage::valeur('simulation.seuil_duo', 25);

        // Le duo divise par deux le nombre de repondants : c'est l'outil quand
        // le groupe sature, et il fait jouer les moins forts avec les plus forts.
        $duo       = $mode === 'duo' || ($mode === 'auto' && $effectif >= $seuilDuo);
        $entites   = $duo ? (int) ceil($effectif / 2) : $effectif;
        $notes     = [];

        if ($duo) {
            $notes[] = "Duo conseille a partir de {$seuilDuo} inscrits : deux fois moins de reponses simultanees, et les debutants jouent avec les confirmes.";
            if ($effectif % 2 === 1) {
                $notes[] = "Effectif impair : une equipe jouera en solo, ou un joueur fera l'arbitre.";
            }
        }

        if ($entites <= $seuilSansPoule) {
            $nbPoules = 0;
            $notes[]  = "Sous {$seuilSansPoule} concurrents, les poules ne filtrent plus rien : on va directement au tableau final.";
        } else {
            $nbPoules = max(1, (int) ceil($entites / $maxParPoule));
            // On vise une taille de tableau qui soit une puissance de deux.
            while ($nbPoules * $qualifies > 16 && $nbPoules > 1) {
                $nbPoules--;
            }
            $nbPoules = max(1, (int) ceil($entites / $maxParPoule)) > $nbPoules
                ? (int) ceil($entites / $maxParPoule)
                : $nbPoules;
        }

        $parPoule    = $nbPoules > 0 ? (int) ceil($entites / $nbPoules) : $entites;
        $tableau     = $nbPoules > 0 ? $nbPoules * $qualifies : $this->puissanceDeDeux($entites);
        $tableau     = min($tableau, $this->puissanceDeDeux($entites));
        $nbQuestions = (int) min($qMax, max($qMin, round($parPoule * $qParJoueur)));

        if ($parPoule > $maxParPoule) {
            $notes[] = "Poule de {$parPoule} joueurs : au-dela de {$maxParPoule}, l'animateur ne peut plus suivre le defilement. Ajoutez une poule.";
        }

        return [
            'effectif'      => $effectif,
            'mode'          => $duo ? 'duo' : 'solo',
            'concurrents'   => $entites,
            'nb_poules'     => $nbPoules,
            'par_poule'     => $parPoule,
            'qualifies'     => $qualifies,
            'taille_tableau' => $tableau,
            'phases'        => $this->phases($tableau),
            'nb_questions'  => $nbQuestions,
            'notes'         => $notes,
        ];
    }

    private function puissanceDeDeux(int $n): int
    {
        $p = 2;
        while ($p * 2 <= max(2, $n)) {
            $p *= 2;
        }

        return min($p, 16);
    }

    /** Nomme les tours, et n'oublie pas la petite finale : le 3e est dote. */
    private function phases(int $tableau): array
    {
        $noms = [16 => 'Huitiemes', 8 => 'Quarts', 4 => 'Demi-finales', 2 => 'Finale'];
        $out  = [];

        for ($t = $tableau; $t >= 2; $t = (int) ($t / 2)) {
            if (isset($noms[$t])) {
                $out[] = ['taille' => $t, 'nom' => $noms[$t], 'matchs' => (int) ($t / 2)];
            }
        }

        $out[] = ['taille' => 2, 'nom' => 'Match pour la 3e place', 'matchs' => 1];

        return $out;
    }

    /**
     * Applique la simulation : cree les poules et repartit les equipes.
     * Rien n'est fige — l'administrateur peut ensuite tout retoucher.
     */
    public function appliquer(Request $request)
    {
        $data = $request->validate([
            'nb_poules'    => ['required', 'integer', 'min:1', 'max:16'],
            'qualifies'    => ['required', 'integer', 'min:1', 'max:16'],
            'nb_questions' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $equipes = Equipe::where('active', true)->get();

        if ($equipes->isEmpty()) {
            return response()->json(['message' => 'Aucune equipe a repartir.'], 422);
        }

        DB::transaction(function () use ($data, $equipes) {
            Poule::query()->delete();

            $poules = collect(range(1, $data['nb_poules']))->map(fn ($i) => Poule::create([
                'nom'          => 'Poule ' . chr(64 + $i),
                'nb_qualifies' => $data['qualifies'],
                'ordre'        => $i,
            ]));

            // Repartition en serpentin plutot qu'en blocs : evite de concentrer
            // les inscrits d'un meme groupe d'amis dans la meme poule.
            $equipes->shuffle()->values()->each(function ($e, $i) use ($poules) {
                $poules[$i % $poules->count()]->equipes()->attach($e->id);
            });

            $poules->each(function ($p) use ($data) {
                Manche::create([
                    'libelle'            => $p->nom,
                    'type'               => 'poule',
                    'poule_id'           => $p->id,
                    'nb_questions_prevu' => $data['nb_questions'],
                    'ordre'              => $p->ordre,
                ]);

                $manche = Manche::where('poule_id', $p->id)->latest()->first();
                $manche->equipes()->attach($p->equipes->pluck('id'));
            });
        });

        return response()->json(['message' => 'Poules et manches creees.']);
    }
}
