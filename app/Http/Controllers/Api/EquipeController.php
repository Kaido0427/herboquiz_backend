<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Models\Participant;
use App\Models\Point;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipeController extends Controller
{
    public function index()
    {
        return Equipe::with('participants')->orderBy('created_at')->get();
    }

    public function store(Request $request)
    {
        $data = $this->valider($request);

        $equipe = DB::transaction(function () use ($data) {
            $e = Equipe::create(['nom' => $data['nom'] ?? null]);
            $e->participants()->sync($data['participants']);

            return $e;
        });

        return response()->json($equipe->load('participants'), 201);
    }

    public function update(Request $request, Equipe $equipe)
    {
        $data = $this->valider($request);

        DB::transaction(function () use ($equipe, $data) {
            $equipe->update(['nom' => $data['nom'] ?? null]);
            $equipe->participants()->sync($data['participants']);
        });

        return response()->json($equipe->load('participants'));
    }

    public function destroy(Equipe $equipe)
    {
        $equipe->delete();

        return response()->json(['message' => 'Equipe supprimee.']);
    }

    /**
     * Constitue les equipes d'un coup a partir des inscrits confirmes.
     * En solo, chaque joueur devient une equipe d'une personne : c'est ce qui
     * permet de rebasculer en duo plus tard sans rien casser.
     */
    public function generer(Request $request)
    {
        $data = $request->validate([
            'mode'      => ['required', 'in:solo,duo'],
            'confirmer' => ['nullable', 'boolean'],
        ]);

        // Garde-fou. Reconstituer les equipes les SUPPRIME, ce qui detache
        // toutes les manches de leurs participants et fait disparaitre les
        // points deja attribues. Un avertissement a l'ecran ne suffit pas :
        // en plein tournoi, un appui malheureux effacerait la competition en
        // cours. On refuse donc tant que ce n'est pas confirme explicitement.
        if (Point::whereNull('annule_le')->exists() && ! $request->boolean('confirmer')) {
            return response()->json([
                'message'          => 'Des points ont deja ete attribues : reconstituer les equipes effacerait le tournoi en cours.',
                'confirmation_requise' => true,
            ], 409);
        }

        $joueurs = Participant::where('confirme', true)->get()->shuffle()->values();

        if ($joueurs->isEmpty()) {
            return response()->json(['message' => 'Aucun inscrit confirme.'], 422);
        }

        DB::transaction(function () use ($joueurs, $data) {
            Equipe::query()->forceDelete();

            $taille = $data['mode'] === 'duo' ? 2 : 1;

            foreach ($joueurs->chunk($taille) as $groupe) {
                $e = Equipe::create();
                $e->participants()->sync($groupe->pluck('id'));
            }
        });

        return response()->json([
            'message' => 'Equipes constituees.',
            'nombre'  => Equipe::count(),
        ]);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom'            => ['nullable', 'string', 'max:80'],
            'participants'   => ['required', 'array', 'min:1', 'max:2'],
            'participants.*' => ['uuid', 'exists:participants,id'],
        ]);
    }
}
