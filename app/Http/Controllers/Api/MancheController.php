<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manche;
use Illuminate\Http\Request;

class MancheController extends Controller
{
    public function index()
    {
        return Manche::with('equipes.participants')
            ->orderBy('ordre')->orderBy('date_prevue')->get();
    }

    public function show(Manche $manche)
    {
        return $manche->load(['equipes.participants', 'questions', 'poule']);
    }

    public function store(Request $request)
    {
        $data   = $this->valider($request);
        $manche = Manche::create($data);
        $manche->equipes()->sync($data['equipes'] ?? []);

        return response()->json($manche->load('equipes'), 201);
    }

    public function update(Request $request, Manche $manche)
    {
        $data = $this->valider($request);
        $manche->update($data);

        if (isset($data['equipes'])) {
            $manche->equipes()->sync($data['equipes']);
        }

        return response()->json($manche->load('equipes'));
    }

    public function destroy(Manche $manche)
    {
        $manche->delete();

        return response()->json(['message' => 'Manche supprimee.']);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'libelle'            => ['required', 'string', 'max:120'],
            'type'               => ['required', 'in:poule,duel'],
            'poule_id'           => ['nullable', 'uuid', 'exists:poules,id'],
            'phase'              => ['nullable', 'string', 'max:40'],
            'date_prevue'        => ['nullable', 'date'],
            'nb_questions_prevu' => ['required', 'integer', 'min:1', 'max:100'],
            'score_cible'        => ['nullable', 'integer', 'min:1', 'max:50'],
            'statut'             => ['nullable', 'in:a_venir,en_cours,terminee'],
            'ordre'              => ['nullable', 'integer'],
            'equipes'            => ['nullable', 'array'],
            'equipes.*'          => ['uuid', 'exists:equipes,id'],
        ]);
    }
}
