<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Models\Manche;
use App\Models\Participant;
use App\Models\Point;
use App\Models\Poule;
use App\Models\Reglage;
use App\Support\Classement;

/**
 * Tout ce que la page publique affiche, sans authentification.
 *
 * Aucune donnee personnelle ne sort d'ici : les numeros de telephone servent a
 * verser les prix, ils n'ont rien a faire sur une page ouverte a tous.
 */
class PublicController extends Controller
{
    public function index()
    {
        $reglages = Reglage::orderBy('ordre')->get()
            ->mapWithKeys(fn ($r) => [$r->cle => $r->valeur_typee]);

        // On expose l'ouverture CALCULEE (interrupteur + fermeture auto a
        // l'heure), pas le simple drapeau : a 20h la page se ferme d'elle-meme.
        $reglages['inscriptions.ouvertes'] = \App\Support\Inscriptions::ouvertes();

        return response()->json([
            'reglages'   => $reglages,
            'classement' => $this->classementGeneral(),
            'poules'     => $this->poules(),
            'manches'    => Manche::with('poule')->orderBy('ordre')->get()
                ->map(fn ($m) => [
                    'id'          => $m->id,
                    'libelle'     => $m->libelle,
                    'type'        => $m->type,
                    'phase'       => $m->phase,
                    'statut'      => $m->statut,
                    'date_prevue' => $m->date_prevue,
                ]),
            'participants' => Participant::where('confirme', true)
                ->orderBy('nom')->get()
                ->map(fn ($p) => ['nom_affiche' => $p->nom_affiche]),
            'nb_inscrits' => Participant::where('confirme', true)->count(),
            'meilleur_marqueur' => $this->meilleurMarqueur(),
        ]);
    }

    /**
     * Composition et classement de chaque poule.
     *
     * On voyait les manches a plat sans jamais savoir QUI etait dans quelle
     * poule ni ou chacun en etait. Meme regle de classement que partout
     * (rapidite, barrage) via le helper. On n'expose que ce qui s'affiche : ni
     * horodatage interne, ni drapeau de barrage a trancher.
     */
    private function poules(): array
    {
        return Poule::with('equipes')->orderBy('ordre')->get()
            ->map(fn ($poule) => [
                'id'         => $poule->id,
                'nom'        => $poule->nom,
                'classement' => collect(Classement::pour($poule->equipes, $poule->manches()->pluck('id')))
                    ->map(fn ($l) => [
                        'libelle'   => $l['libelle'],
                        'points'    => $l['points'],
                        'rang'      => $l['rang'],
                        'ex_aequo'  => $l['ex_aequo'],
                        'departage' => $l['departage'],
                        'elimine'   => $l['elimine'],
                        'penalite'  => $l['penalite'],
                    ])->all(),
            ])->all();
    }

    /**
     * Meilleur marqueur, calcule sur les POULES uniquement.
     *
     * On peut beaucoup marquer sans aller au bout : le tableau final
     * recompense celui qui gagne ses duels, pas celui qui repond le mieux.
     *
     * Le calcul se limite aux poules a dessein. Sur le total brut, celui qui
     * atteint la finale joue plus de manches et accumule mecaniquement plus de
     * points : le prix reviendrait au vainqueur, ce qui viderait la
     * recompense de son sens. En poules, tout le monde joue le meme nombre de
     * questions, donc les totaux sont comparables.
     */
    private function meilleurMarqueur(): ?array
    {
        $manchesPoule = Manche::where('type', 'poule')->pluck('id');

        if ($manchesPoule->isEmpty()) {
            return null;
        }

        $totaux = Point::whereIn('manche_id', $manchesPoule)
            ->whereNull('annule_le')
            ->where('est_penalite', false)   // le marqueur, c'est ce qu'on MARQUE : sans les penalites
            ->selectRaw('equipe_id, SUM(points) AS total')
            ->groupBy('equipe_id')
            ->orderByDesc('total')
            ->first();

        if (! $totaux || $totaux->total <= 0) {
            return null;
        }

        $equipe = Equipe::with('participants')->find($totaux->equipe_id);

        return [
            'libelle' => $equipe?->libelle,
            'points'  => (int) $totaux->total,
        ];
    }

    private function classementGeneral(): array
    {
        $totaux = Point::whereNull('annule_le')
            ->selectRaw('equipe_id, SUM(points) AS total')
            ->groupBy('equipe_id')
            ->pluck('total', 'equipe_id');

        $penalites = Point::whereNull('annule_le')->where('est_penalite', true)
            ->selectRaw('equipe_id, SUM(points) AS p')
            ->groupBy('equipe_id')
            ->pluck('p', 'equipe_id');

        return Equipe::with('participants')->get()
            ->map(fn ($e) => [
                'libelle'  => $e->libelle,
                'points'   => (int) ($totaux[$e->id] ?? 0),
                'penalite' => (int) ($penalites[$e->id] ?? 0),
                'elimine'  => $e->elimine_le !== null,
            ])
            ->sortByDesc('points')->values()->all();
    }
}
