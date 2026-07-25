<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function index(Request $request)
    {
        return Participant::when(
            $request->boolean('confirmes_seuls'),
            fn ($q) => $q->where('confirme', true)
        )->orderBy('nom')->get();
    }

    public function store(Request $request)
    {
        return response()->json(Participant::create($this->valider($request)), 201);
    }

    public function update(Request $request, Participant $participant)
    {
        $participant->update($this->valider($request, $participant));

        return response()->json($participant);
    }

    /**
     * Eliminer / reintegrer un joueur (bascule).
     *
     * Un joueur pas serieux : on l'ecarte de CETTE edition, mais il reste en
     * base et pourra revenir. Reversible. C'est une elimination, pas une
     * suppression. Ouvert a tous les admins.
     */
    public function eliminer(Request $request, Participant $participant)
    {
        if ($participant->elimine_le) {
            $participant->update(['elimine_le' => null, 'elimine_par' => null]);
            $message = 'Joueur reintegre.';
        } else {
            $participant->update([
                'elimine_le'  => now(),
                'elimine_par' => $request->user()?->nom,
            ]);
            $message = 'Joueur elimine.';
        }

        return response()->json(['message' => $message, 'elimine' => $participant->elimine_le !== null]);
    }

    /**
     * Supprimer definitivement un joueur de la base.
     *
     * Reserve au proprietaire du projet : effacer une inscription est bien plus
     * lourd qu'une elimination (reversible). Les autres admins eliminent, ils
     * ne suppriment pas. Verrou serveur, pas seulement un bouton masque.
     */
    public function destroy(Request $request, Participant $participant)
    {
        if ($request->user()?->nom !== config('herboquiz.proprietaire')) {
            return response()->json([
                'message' => 'La suppression est reservee au proprietaire. Utilisez « Eliminer ».',
            ], 403);
        }

        $participant->delete();

        return response()->json(['message' => 'Participant retire.']);
    }

    private function valider(Request $request, ?Participant $p = null): array
    {
        return $request->validate([
            'nom'       => ['required', 'string', 'max:80'],
            'prenom'    => ['nullable', 'string', 'max:80'],
            // Le pseudo affiche dans le groupe : c'est lui que l'animateur
            // reconnait en direct, il compte plus que l'etat civil.
            'pseudo'    => ['nullable', 'string', 'max:80'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'confirme'  => ['boolean'],
            'note'      => ['nullable', 'string', 'max:500'],
        ]);
    }
}
