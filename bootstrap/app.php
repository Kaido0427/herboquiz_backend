<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => \App\Http\Middleware\RoleAdmin::class]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sans cela, une requete non authentifiee produit une 500 : Laravel
        // cherche a rediriger vers une page de connexion qui n'existe pas dans
        // une API. Le frontend, qui nettoie la session sur 401, ne verrait
        // jamais le signal et laisserait l'utilisateur bloque.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json(['message' => 'Non authentifie.'], 401);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
