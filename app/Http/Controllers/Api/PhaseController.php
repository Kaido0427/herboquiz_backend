<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manche;
use App\Models\Poule;
use App\Models\Reglage;
use Illuminate\Support\Facades\DB;

/**
 * Enchainement des tours apres les poules.
 *
 * C'est la piece qui relie les phases : tant qu'elle n'existe pas, on sait qui
 * a gagne sa poule mais rien ne fabrique les quarts, puis les demies, puis la
 * finale. L'organisateur devrait creer chaque duel a la main en relisant les
 * classements — c'est long et c'est la que naissent les erreurs.
 *
 * Le tour suivant se deduit toujours de l'etat reel du tournoi : on ne demande
 * jamais a l'organisateur de ressaisir qui est qualifie.
 */
class PhaseController extends Controller
{
    /** Ce qui est jouable, ce qui est fini, et ce qu'on peut generer maintenant. */
    public function etat()
    {
        $poules = Poule::with('equipes')->orderBy('ordre')->get();
        $manchesPoule = Manche::where('type', 'poule')->get();
        $duels = Manche::where('type', 'duel')->orderBy('ordre')->get();

        $poulesFinies = $manchesPoule->count() > 0
            && $manchesPoule->every(fn ($m) => $m->statut === 'terminee');

        $dernierTour = $duels->max('ordre');
        $duelsDernierTour = $duels->where('ordre', $dernierTour);
        $tourFini = $duelsDernierTour->count() > 0
            && $duelsDernierTour->every(fn ($m) => $m->statut === 'terminee');

        // On peut generer si les poules sont finies et qu'aucun duel n'existe,
        // ou si le dernier tour de duels est termine et qu'il reste plus d'un
        // qualifie.
        $peutGenerer = false;
        $prochain = null;

        if ($poules->count() > 0 && $poulesFinies && $duels->isEmpty()) {
            $peutGenerer = true;
            $prochain = $this->nommer(count($this->qualifiesDesPoules()));
        } elseif ($tourFini) {
            $vainqueurs = $this->vainqueurs($duelsDernierTour);
            if (count($vainqueurs) >= 2) {
                $peutGenerer = true;
                $prochain = $this->nommer(count($vainqueurs));
            }
        }

        return response()->json([
            'poules_terminees'   => $poulesFinies,
            'nb_poules'          => $poules->count(),
            'manches_poule'      => $manchesPoule->count(),
            'duels_existants'    => $duels->count(),
            'dernier_tour_fini'  => $tourFini,
            'peut_generer'       => $peutGenerer,
            'prochain_tour'      => $prochain,
            'tours' => $duels->groupBy('ordre')->map(fn ($g, $ordre) => [
                'ordre'   => (int) $ordre,
                'nom'     => $g->first()->phase,
                'matchs'  => $g->count(),
                'termine' => $g->every(fn ($m) => $m->statut === 'terminee'),
            ])->values(),
        ]);
    }

    /**
     * Cree le tour suivant a partir de l'etat reel.
     *
     * Les qualifies viennent soit des classements de poule, soit des vainqueurs
     * du tour precedent. Personne ne ressaisit rien.
     */
    public function generer()
    {
        $duels = Manche::where('type', 'duel')->get();
        $dernierTour = $duels->max('ordre');
        $duelsDernierTour = $duels->where('ordre', $dernierTour);

        if ($duels->isEmpty()) {
            $qualifies = $this->qualifiesDesPoules();
            $ordre = 1;
        } else {
            if (! $duelsDernierTour->every(fn ($m) => $m->statut === 'terminee')) {
                return response()->json(['message' => 'Le tour en cours n\'est pas termine.'], 422);
            }
            $qualifies = $this->vainqueurs($duelsDernierTour);
            $ordre = $dernierTour + 1;
        }

        if (count($qualifies) < 2) {
            return response()->json(['message' => 'Pas assez de qualifies pour un tour de plus.'], 422);
        }

        $nom = $this->nommer(count($qualifies));
        $scoreCible = (int) Reglage::valeur(
            count($qualifies) === 2 ? 'jeu.score_cible_finale' : 'jeu.score_cible_duel',
            5,
        );

        // Date proposee : on decale du nombre de jours configure et on pose
        // l'heure habituelle des manches. C'est une PROPOSITION — chaque date
        // reste modifiable ensuite, un tournoi se decale toujours un peu.
        $jours = (int) Reglage::valeur('jeu.jours_entre_tours', 1);
        $heure = (string) Reglage::valeur('jeu.heure_manche', '18:00');
        $dateTour = now()->addDays($jours * $ordre)->setTimeFromTimeString($heure . ':00');

        $crees = DB::transaction(function () use ($qualifies, $ordre, $nom, $scoreCible, $dateTour) {
            $liste = array_values($qualifies);
            $faits = [];

            // Tete de serie contre dernier qualifie : le premier de sa poule ne
            // doit pas tomber sur un autre premier des le premier tour.
            for ($i = 0, $j = count($liste) - 1; $i < $j; $i++, $j--) {
                $manche = Manche::create([
                    'libelle'            => $nom . ' ' . ($i + 1),
                    'type'               => 'duel',
                    'phase'              => $nom,
                    'nb_questions_prevu' => $scoreCible * 3,
                    'score_cible'        => $scoreCible,
                    'date_prevue'        => $dateTour,
                    'ordre'              => $ordre,
                ]);
                $manche->equipes()->attach([$liste[$i], $liste[$j]]);
                $faits[] = $manche->libelle;
            }

            return $faits;
        });

        // Le 3e est dote, il faut donc un match pour la 3e place. On ne peut le
        // creer qu'ICI : ses participants sont les PERDANTS des demi-finales,
        // inconnus tant que celles-ci ne sont pas jouees. Le creer en meme temps
        // que les demies produirait un match sans equipes.
        if ($nom === 'Finale' && $duelsDernierTour->count() === 2) {
            $perdants = [];
            foreach ($duelsDernierTour as $demie) {
                $classement = $demie->classement();
                if (count($classement) >= 2) {
                    $perdants[] = $classement[1]['equipe_id'];
                }
            }

            if (count($perdants) === 2) {
                $petite = Manche::create([
                    'libelle'            => 'Match pour la 3e place',
                    'type'               => 'duel',
                    'phase'              => 'petite_finale',
                    'nb_questions_prevu' => $scoreCible * 3,
                    'score_cible'        => $scoreCible,
                    'date_prevue'        => $dateTour,
                    'ordre'              => $ordre,
                ]);
                $petite->equipes()->attach($perdants);
                $crees[] = $petite->libelle;
            }
        }

        return response()->json(['tour' => $nom, 'matchs' => $crees]);
    }

    /** Les meilleurs de chaque poule, selon le nombre de qualifies configure. */
    private function qualifiesDesPoules(): array
    {
        $out = [];

        foreach (Poule::with('equipes')->orderBy('ordre')->get() as $poule) {
            $manches = Manche::where('poule_id', $poule->id)->get();

            $totaux = [];
            foreach ($manches as $m) {
                foreach ($m->classement() as $ligne) {
                    $totaux[$ligne['equipe_id']] = ($totaux[$ligne['equipe_id']] ?? 0) + $ligne['points'];
                }
            }

            arsort($totaux);
            $out = array_merge($out, array_slice(array_keys($totaux), 0, $poule->nb_qualifies));
        }

        return $out;
    }

    /** Le vainqueur d'un duel est celui qui mene au classement de la manche. */
    private function vainqueurs($duels): array
    {
        $out = [];

        foreach ($duels as $duel) {
            if ($duel->phase === 'petite_finale') {
                continue;   // ne qualifie pour rien
            }
            $classement = $duel->classement();
            if (! empty($classement)) {
                $out[] = $classement[0]['equipe_id'];
            }
        }

        return $out;
    }

    private function nommer(int $nbQualifies): string
    {
        return match (true) {
            $nbQualifies >= 16 => 'Huitiemes',
            $nbQualifies >= 8  => 'Quarts',
            $nbQualifies >= 4  => 'Demi-finales',
            default            => 'Finale',
        };
    }
}
