<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Acces;
use App\Models\SessionAcces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class AccesController extends Controller
{
    public function index()
    {
        return Acces::orderBy('role')->get(['id', 'role', 'code_clair', 'regenere_le']);
    }

    /**
     * Regeneration d'un code.
     *
     * Les animateurs changent d'un match a l'autre : il faut pouvoir couper
     * l'acces d'hier. Regenerer sans revoquer les jetons deja emis serait
     * purement cosmetique — l'ancien animateur resterait connecte. On fait donc
     * les deux dans la meme transaction.
     */
    public function regenerer(Request $request, Acces $acces)
    {
        $code = Acces::genererCode();

        DB::transaction(function () use ($acces, $code) {
            $acces->definirCode($code);

            $sessions = SessionAcces::where('role', $acces->role)->pluck('id');
            PersonalAccessToken::where('tokenable_type', SessionAcces::class)
                ->whereIn('tokenable_id', $sessions)
                ->delete();
        });

        return response()->json([
            'role'       => $acces->role,
            'code_clair' => $code,
            'message'    => 'Code regenere. Les sessions ouvertes avec l\'ancien code sont fermees.',
        ]);
    }

    public function definir(Request $request, Acces $acces)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:32'],
        ]);

        $code = strtoupper(trim($data['code']));
        $acces->definirCode($code);

        return response()->json(['role' => $acces->role, 'code_clair' => $code]);
    }

    /** Qui s'est connecte, et quand — utile pour trancher un litige. */
    public function sessions()
    {
        return SessionAcces::orderByDesc('derniere_activite')->limit(100)->get();
    }
}
