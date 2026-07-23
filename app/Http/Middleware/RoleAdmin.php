<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Garde-fou serveur.
 *
 * Masquer un bouton dans l'interface n'est pas une autorisation : sans ce
 * controle, un animateur pourrait appeler directement l'API d'administration.
 */
class RoleAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Reserve aux administrateurs.'], 403);
        }

        return $next($request);
    }
}
