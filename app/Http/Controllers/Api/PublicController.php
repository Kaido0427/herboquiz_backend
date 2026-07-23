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
        ]);
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
