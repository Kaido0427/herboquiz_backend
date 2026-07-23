<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Models\Manche;
use App\Models\Participant;
use App\Models\Poule;

/**
 * Etat de la chaine de preparation.
 *
 * Les ecrans Participants, Equipes et Format fonctionnaient isolement : rien
 * ne montrait qu'ils dependent l'un de l'autre. Ajouter un inscrit apres avoir
 * constitue les equipes le laissait sans equipe, en silence ; regenerer les
 * equipes apres avoir applique le format laissait les poules sur les
 * anciennes. On decouvrait le probleme le soir du match.
 *
 * Ce point unique dit ou en est la chaine et ce qui ne suit plus.
 */
class PreparationController extends Controller
{
    public function index()
    {
        $confirmes = Participant::where('confirme', true)->get();
        $nonConfirmes = Participant::where('confirme', false)->count();
        $equipes = Equipe::with('participants')->get();
        $poules = Poule::with('equipes')->get();
        $manchesPoule = Manche::where('type', 'poule')->count();

        $dansEquipe = $equipes->flatMap(fn ($e) => $e->participants->pluck('id'))->unique();
        $orphelins = $confirmes->reject(fn ($p) => $dansEquipe->contains($p->id));

        $enPoule = $poules->flatMap(fn ($p) => $p->equipes->pluck('id'))->unique();
        $equipesHorsPoule = $equipes->reject(fn ($e) => $enPoule->contains($e->id));

        $alertes = [];

        if ($orphelins->isNotEmpty()) {
            $alertes[] = [
                'gravite' => 'alerte',
                'cle'     => 'inscrits_sans_equipe',
                'nombre'  => $orphelins->count(),
                'noms'    => $orphelins->take(8)->map->nom_affiche->values(),
            ];
        }

        if ($nonConfirmes > 0) {
            $alertes[] = ['gravite' => 'info', 'cle' => 'non_confirmes', 'nombre' => $nonConfirmes];
        }

        if ($equipes->isNotEmpty() && $poules->isNotEmpty() && $equipesHorsPoule->isNotEmpty()) {
            $alertes[] = [
                'gravite' => 'alerte',
                'cle'     => 'equipes_sans_poule',
                'nombre'  => $equipesHorsPoule->count(),
            ];
        }

        // Etape suivante conseillee : ce qui manque pour avancer, dans l'ordre.
        $prochaine = match (true) {
            $confirmes->isEmpty()          => 'ajouter_participants',
            $equipes->isEmpty()            => 'constituer_equipes',
            $orphelins->isNotEmpty()       => 'reconstituer_equipes',
            $poules->isEmpty()             => 'appliquer_format',
            $manchesPoule === 0            => 'appliquer_format',
            default                        => 'preparer_questions',
        };

        return response()->json([
            'participants_confirmes' => $confirmes->count(),
            'participants_total'     => $confirmes->count() + $nonConfirmes,
            'equipes'                => $equipes->count(),
            'poules'                 => $poules->count(),
            'manches_poule'          => $manchesPoule,
            'alertes'                => $alertes,
            'prochaine_etape'        => $prochaine,
        ]);
    }
}
