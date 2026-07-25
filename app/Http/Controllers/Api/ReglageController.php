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

        $cles = collect($data['reglages'])->pluck('cle');
        if ($this->toucheAuProprietaire($request, $cles)) {
            return $this->refusProprietaire();
        }

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

        if ($this->groupeReserve($data['groupe']) && ! $this->estProprietaire($request)) {
            return $this->refusProprietaire();
        }

        return response()->json(Reglage::create($data), 201);
    }

    public function destroy(Request $request, Reglage $reglage)
    {
        if ($this->groupeReserve($reglage->groupe) && ! $this->estProprietaire($request)) {
            return $this->refusProprietaire();
        }

        $reglage->delete();

        return response()->json(['message' => 'Reglage supprime.']);
    }

    // -----------------------------------------------------------------------
    // Reglages reserves au proprietaire (signature NovafriQ, bloc promo).
    // Le code admin est partage entre six personnes : ce garde-fou empeche
    // qu'un autre admin retire la contrepartie de l'infra offerte. Serveur ET
    // interface, car masquer un bouton n'a jamais empeche un appel d'API.
    // -----------------------------------------------------------------------

    private function estProprietaire(Request $request): bool
    {
        return $request->user()?->nom === config('herboquiz.proprietaire');
    }

    private function groupeReserve(?string $groupe): bool
    {
        return in_array($groupe, config('herboquiz.groupes_proprietaire', []), true);
    }

    /** Vrai si l'une des cles touchees appartient a un groupe reserve. */
    private function toucheAuProprietaire(Request $request, $cles): bool
    {
        if ($this->estProprietaire($request)) {
            return false;
        }

        return Reglage::whereIn('cle', collect($cles)->all())
            ->whereIn('groupe', config('herboquiz.groupes_proprietaire', []))
            ->exists();
    }

    private function refusProprietaire()
    {
        return response()->json([
            'message' => 'Ces reglages sont reserves au proprietaire du projet.',
        ], 403);
    }
}
