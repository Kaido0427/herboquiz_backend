<?php

use App\Http\Controllers\Api\AccesController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EquipeController;
use App\Http\Controllers\Api\MancheController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ReglageController;
use App\Http\Controllers\Api\ScoringController;
use App\Http\Controllers\Api\SimulationController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Public — aucune authentification. Les joueurs n'ont jamais besoin de se
// connecter : ils jouent dans Messenger, ils viennent juste lire le classement.
// ---------------------------------------------------------------------------
Route::get('public', [PublicController::class, 'index']);

Route::post('connexion', [AuthController::class, 'connexion'])
    ->middleware('throttle:10,1');   // un code court se devine par force brute

// ---------------------------------------------------------------------------
// Connecte — admin ou animateur
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('moi', [AuthController::class, 'moi']);
    Route::post('deconnexion', [AuthController::class, 'deconnexion']);

    // Ce que l'animateur a le droit de faire, et rien de plus.
    Route::get('manches/{manche}/animation', [ScoringController::class, 'manche']);
    Route::post('manches/{manche}/point', [ScoringController::class, 'attribuer']);
    Route::post('manches/{manche}/annuler', [ScoringController::class, 'annuler']);
    Route::post('manches/{manche}/terminer', [ScoringController::class, 'terminer']);
    Route::get('manches', [MancheController::class, 'index']);
    Route::get('manches/{manche}', [MancheController::class, 'show']);
    Route::get('questions', [QuestionController::class, 'index']);

    // -----------------------------------------------------------------------
    // Administration. Le controle est ici, cote serveur : masquer un bouton
    // dans l'interface n'a jamais empeche personne d'appeler l'API.
    // -----------------------------------------------------------------------
    Route::middleware('admin')->group(function () {
        Route::get('reglages', [ReglageController::class, 'index']);
        Route::put('reglages', [ReglageController::class, 'majLot']);
        Route::post('reglages', [ReglageController::class, 'store']);
        Route::delete('reglages/{reglage}', [ReglageController::class, 'destroy']);

        Route::apiResource('participants', ParticipantController::class)->except(['show']);

        Route::post('equipes/generer', [EquipeController::class, 'generer']);
        Route::apiResource('equipes', EquipeController::class)->except(['show']);

        Route::apiResource('manches', MancheController::class)->only(['store', 'update', 'destroy']);

        Route::post('questions/lot', [QuestionController::class, 'storeLot']);
        Route::apiResource('questions', QuestionController::class)->except(['index', 'show']);

        Route::post('simulation', [SimulationController::class, 'simuler']);
        Route::post('simulation/appliquer', [SimulationController::class, 'appliquer']);

        Route::get('acces', [AccesController::class, 'index']);
        Route::get('acces/sessions', [AccesController::class, 'sessions']);
        Route::post('acces/{acces}/regenerer', [AccesController::class, 'regenerer']);
        Route::put('acces/{acces}', [AccesController::class, 'definir']);
    });
});
