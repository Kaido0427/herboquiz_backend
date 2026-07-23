<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Point;
use App\Models\Reglage;

/**
 * Parcours d'un participant.
 *
 * Tout se deduit du journal des points, qui portait deja l'information : la
 * manche, l'instant et l'auteur de chaque attribution. Aucun total n'est
 * stocke, donc ces chiffres ne peuvent pas diverger du classement.
 */
class PerformanceController extends Controller
{
    public function show(Participant $participant)
    {
        $participant->load('equipes.participants');
        $equipes = $participant->equipes->pluck('id');

        $points = Point::whereIn('equipe_id', $equipes)
            ->whereNull('annule_le')
            ->with('manche')
            ->orderBy('created_at')
            ->get();

        $parReponse = max(1, (int) Reglage::valeur('jeu.points_bonne_reponse', 1));

        $manches = $points->groupBy('manche_id')->map(function ($lot) use ($parReponse) {
            $manche = $lot->first()->manche;
            $total = $lot->sum('points');

            return [
                'manche'          => $manche?->libelle,
                'type'            => $manche?->type,
                'date'            => $manche?->date_prevue,
                'points'          => $total,
                'bonnes_reponses' => intdiv($total, $parReponse),
                'rang'            => $this->rangDans($manche, $lot->first()->equipe_id),
            ];
        })->values();

        $total = $points->sum('points');

        return response()->json([
            'participant' => [
                'id'           => $participant->id,
                'nom_affiche'  => $participant->nom_affiche,
                'nom_complet'  => $participant->nom_complet,
                'confirme'     => $participant->confirme,
                'auto_inscrit' => $participant->auto_inscrit,
            ],
            'equipes' => $participant->equipes->map(fn ($e) => [
                'libelle'    => $e->libelle,
                'coequipier' => $e->participants
                    ->reject(fn ($p) => $p->id === $participant->id)
                    ->map->nom_affiche->values(),
            ]),
            'total_points'      => $total,
            'bonnes_reponses'   => intdiv($total, $parReponse),
            'manches_jouees'    => $manches->count(),
            'meilleure_manche'  => $manches->sortByDesc('points')->first(),
            'manches'           => $manches,
            // Journal detaille : c'est ce qui permet de repondre a « qui m'a
            // enleve ce point ? » sans avoir a se souvenir.
            'journal' => $points->map(fn ($p) => [
                'manche'       => $p->manche?->libelle,
                'points'       => $p->points,
                'attribue_par' => $p->attribue_par,
                'le'           => $p->created_at,
            ])->reverse()->take(30)->values(),
        ]);
    }

    /** Rang de l'equipe dans le classement de cette manche. */
    private function rangDans($manche, string $equipeId): ?int
    {
        if (! $manche) {
            return null;
        }

        foreach ($manche->classement() as $i => $ligne) {
            if ($ligne['equipe_id'] === $equipeId) {
                return $i + 1;
            }
        }

        return null;
    }
}
