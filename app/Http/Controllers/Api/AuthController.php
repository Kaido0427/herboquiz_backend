<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Acces;
use App\Models\Membre;
use App\Models\SessionAcces;
use Illuminate\Http\Request;

/**
 * Connexion par code partage + nom saisi.
 *
 * Le code dit CE QUE vous avez le droit de faire (admin ou animateur), le nom
 * dit QUI l'a fait. C'est ce couple qui permet de garder un seul code a
 * distribuer tout en sachant, plus tard, qui a attribue quel point.
 */
class AuthController extends Controller
{
    /**
     * Premiere etape : on valide le code seul, et on renvoie les noms deja
     * connus pour ce role. L'interesse choisit alors son nom au lieu de le
     * retaper — ecrire « Ekson » un soir et « Eckson » le lendemain rendrait la
     * tracabilite des points inexploitable.
     *
     * Les noms ne sortent qu'a quelqu'un qui possede deja le code.
     */
    public function verifier(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $code = strtoupper(trim($data['code']));

        foreach (Acces::all() as $acces) {
            if ($acces->verifierCode($code)) {
                return response()->json([
                    'role' => $acces->role,
                    'noms' => Membre::where('role', $acces->role)
                        ->orderBy('ordre')->orderBy('nom')->pluck('nom'),
                ]);
            }
        }

        return response()->json(['message' => 'Code invalide.'], 401);
    }

    public function connexion(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'nom'  => ['required', 'string', 'max:60'],
        ]);

        $code = strtoupper(trim($data['code']));

        foreach (Acces::all() as $acces) {
            if (! $acces->verifierCode($code)) {
                continue;
            }

            $session = SessionAcces::create([
                'role'              => $acces->role,
                'nom'               => trim($data['nom']),
                'derniere_activite' => now(),
            ]);

            // Un nom saisi librement rejoint la liste : la prochaine connexion
            // le proposera, et l'orthographe restera stable.
            Membre::firstOrCreate(['nom' => $session->nom, 'role' => $acces->role]);

            return response()->json([
                'jeton' => $session->createToken('herboquiz', [$acces->role])->plainTextToken,
                'role'  => $acces->role,
                'nom'   => $session->nom,
            ]);
        }

        return response()->json(['message' => 'Code invalide.'], 401);
    }

    public function moi(Request $request)
    {
        $s = $request->user();

        return response()->json(['role' => $s->role, 'nom' => $s->nom]);
    }

    public function deconnexion(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Deconnecte.']);
    }
}
