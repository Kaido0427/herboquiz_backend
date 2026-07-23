<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membre;
use Illuminate\Http\Request;

class MembreController extends Controller
{
    public function index()
    {
        return Membre::orderBy('role')->orderBy('ordre')->orderBy('nom')->get();
    }

    public function store(Request $request)
    {
        return response()->json(Membre::create($this->valider($request)), 201);
    }

    /** Chacun peut corriger son propre nom : ce sont des pseudonymes de groupe. */
    public function update(Request $request, Membre $membre)
    {
        $membre->update($this->valider($request));

        return response()->json($membre);
    }

    public function destroy(Membre $membre)
    {
        $membre->delete();

        return response()->json(['message' => 'Membre retire.']);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom'   => ['required', 'string', 'max:60'],
            'role'  => ['required', 'in:admin,modo'],
            'ordre' => ['nullable', 'integer'],
        ]);
    }
}
