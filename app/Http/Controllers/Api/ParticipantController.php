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

    public function destroy(Participant $participant)
    {
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
