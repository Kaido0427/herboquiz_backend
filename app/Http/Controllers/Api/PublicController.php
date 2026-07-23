<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Models\Manche;
use App\Models\Participant;
use App\Models\Point;
use App\Models\Reglage;

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
        return response()->json([
            'reglages'   => Reglage::orderBy('ordre')->get()
                ->mapWithKeys(fn ($r) => [$r->cle => $r->valeur_typee]),
            'classement' => $this->classementGeneral(),
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

        return Equipe::with('participants')->get()
            ->map(fn ($e) => [
                'libelle' => $e->libelle,
                'points'  => (int) ($totaux[$e->id] ?? 0),
            ])
            ->sortByDesc('points')->values()->all();
    }
}
