<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reglage;
use Illuminate\Http\Request;

class ReglageController extends Controller
{
    public function index()
    {
        return Reglage::orderBy('groupe')->orderBy('ordre')->get()
            ->groupBy('groupe');
    }

    /** Mise a jour en lot : l'admin enregistre un onglet entier d'un coup. */
    public function majLot(Request $request)
    {
        $data = $request->validate([
            'reglages'             => ['required', 'array'],
            'reglages.*.cle'       => ['required', 'string'],
            'reglages.*.valeur'    => ['nullable'],
        ]);

        foreach ($data['reglages'] as $r) {
            Reglage::where('cle', $r['cle'])->update(['valeur' => $r['valeur']]);
        }

        return response()->json(['message' => 'Reglages enregistres.']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cle'     => ['required', 'string', 'unique:reglages,cle'],
            'libelle' => ['required', 'string'],
            'valeur'  => ['nullable'],
            'type'    => ['required', 'in:texte,nombre,booleen,json,markdown'],
            'groupe'  => ['required', 'string'],
            'aide'    => ['nullable', 'string'],
        ]);

        return response()->json(Reglage::create($data), 201);
    }

    public function destroy(Reglage $reglage)
    {
        $reglage->delete();

        return response()->json(['message' => 'Reglage supprime.']);
    }
}
