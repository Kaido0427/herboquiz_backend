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

        $confirmes = Participant::where('confirme', true)->count();

        // Si les equipes sont DEJA constituees, on reflete la realite : les
        // concurrents sont ces equipes, et le mode est le leur (duo si elles ont
        // deux equipiers). Reproposer « solo » n'aurait aucun sens ici — le
        // choix solo/duo se fait une seule fois, a la constitution des equipes.
        $equipes = Equipe::where('active', true)->withCount('participants')->get();
        if ($equipes->isNotEmpty()) {
            $duo = $equipes->contains(fn ($e) => $e->participants_count > 1);

            return response()->json($this->calculer($equipes->count(), $duo, $confirmes, reel: true));
        }

        // Pas encore d'equipes : planification a partir de l'effectif et du choix.
        $effectif = $data['effectif'] ?? $confirmes;
        $mode     = $data['mode'] ?? 'auto';
        $seuilDuo = (int) Reglage::valeur('simulation.seuil_duo', 25);
        $duo      = $mode === 'duo' || ($mode === 'auto' && $effectif >= $seuilDuo);
        $entites  = $duo ? (int) ceil($effectif / 2) : $effectif;

        return response()->json($this->calculer($entites, $duo, $effectif, reel: false));
    }

    private function calculer(int $entites, bool $duo, int $effectif, bool $reel): array
    {
        $maxParPoule    = (int) Reglage::valeur('simulation.max_par_poule', 20);
        $qualifies      = (int) Reglage::valeur('simulation.qualifies_par_poule', 4);
        $qParJoueur     = (float) Reglage::valeur('simulation.questions_par_joueur', 1);
        $qMin           = (int) Reglage::valeur('simulation.questions_min', 12);
        $qMax           = (int) Reglage::valeur('simulation.questions_max', 20);
        $seuilSansPoule = (int) Reglage::valeur('simulation.seuil_sans_poules', 16);
        $seuilDuo       = (int) Reglage::valeur('simulation.seuil_duo', 25);

        $notes = [];

        if ($reel) {
            // Format base sur les equipes reelles : pas de conseil solo/duo, la
            // decision est deja prise et concretisee.
            $notes[] = "Format calcule sur les {$entites} equipes deja constituees.";
        } elseif ($duo) {
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
            'reel'          => $reel,
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

        // Refuser d'ecraser un tournoi deja commence : appliquer un nouveau
        // format effacerait les manches en cours et les points avec elles.
        if (\App\Models\Point::whereNull('annule_le')->exists()) {
            return response()->json([
                'message' => 'Des points ont deja ete attribues : changer le format effacerait le tournoi en cours.',
            ], 409);
        }

        DB::transaction(function () use ($data, $equipes) {
            // Les anciennes manches de poule doivent partir avec leurs poules,
            // sinon chaque nouvelle simulation empile des manches fantomes que
            // plus rien ne rattache a un format.
            Manche::where('type', 'poule')->forceDelete();
            Poule::query()->forceDelete();

            $poules = collect(range(1, $data['nb_poules']))->map(fn ($i) => Poule::create([
                'nom'          => 'Poule ' . chr(64 + $i),
                'nb_qualifies' => $data['qualifies'],
                'ordre'        => $i,
            ]));

            // Une manche (vide) par poule...
            $poules->each(fn ($p) => Manche::create([
                'libelle'            => $p->nom,
                'type'               => 'poule',
                'poule_id'           => $p->id,
                'nb_questions_prevu' => $data['nb_questions'],
                'ordre'              => $p->ordre,
            ]));

            // ...puis le tirage partage remplit poules ET manches, en serpentin.
            \App\Support\Tirage::repartir(Poule::orderBy('ordre')->get(), $equipes);
        });

        return response()->json(['message' => 'Poules et manches creees.']);
    }

    /**
     * Re-tirer les poules SANS changer le format.
     *
     * Meme nombre de poules, memes reglages : on re-melange seulement la
     * repartition des equipes, en serpentin. Utile pour retirer jusqu'a un
     * tirage qui convient. Refuse des qu'un point existe (ce serait effacer le
     * tournoi en cours).
     */
    public function retirer()
    {
        if (\App\Models\Point::whereNull('annule_le')->exists()) {
            return response()->json([
                'message' => 'Des points ont deja ete attribues : re-tirer effacerait le tournoi en cours.',
            ], 409);
        }

        $poules  = Poule::orderBy('ordre')->get();
        $equipes = Equipe::where('active', true)->get();

        if ($poules->isEmpty()) {
            return response()->json(['message' => 'Aucune poule. Appliquez d\'abord un format.'], 422);
        }
        if ($equipes->isEmpty()) {
            return response()->json(['message' => 'Aucune equipe a repartir.'], 422);
        }

        DB::transaction(fn () => \App\Support\Tirage::repartir($poules, $equipes));

        return response()->json(['message' => 'Poules re-tirees.']);
    }
}
