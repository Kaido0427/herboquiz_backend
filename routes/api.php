<?php

use App\Http\Controllers\Api\AccesController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EquipeController;
use App\Http\Controllers\Api\InscriptionController;
use App\Http\Controllers\Api\MancheController;
use App\Http\Controllers\Api\MembreController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\PhaseController;
use App\Http\Controllers\Api\PreparationController;
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

// Inscription en autonomie. Route publique, donc exposee : la limitation de
// debit n'est pas un confort. Sans elle, cent inscriptions envoyees en une
// minute fausseraient le dimensionnement du format avant le coup d'envoi.
Route::post('inscription/verifier', [InscriptionController::class, 'verifier'])
    ->middleware('throttle:20,1');
Route::post('inscription', [InscriptionController::class, 'inscrire'])
    ->middleware('throttle:5,10');

Route::post('connexion/verifier', [AuthController::class, 'verifier'])
    ->middleware('throttle:10,1');
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
    Route::post('manches/{manche}/rouvrir', [ScoringController::class, 'rouvrir']);
    Route::get('manches', [MancheController::class, 'index']);
    Route::get('manches/{manche}', [MancheController::class, 'show']);
    // Preparer les questions fait partie du travail de l'animateur : c'est lui
    // qui tient la manche. Il garde en revanche interdiction de toucher aux
    // participants, aux reglages, au format et aux acces.
    Route::get('participants/{participant}/performances', [PerformanceController::class, 'show']);
    Route::get('questions', [QuestionController::class, 'index']);
    Route::post('questions/lot', [QuestionController::class, 'storeLot']);
    Route::post('questions/affecter', [QuestionController::class, 'affecter']);
    Route::apiResource('questions', QuestionController::class)->except(['index', 'show']);

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

        // Routes ecrites a la main plutot qu'apiResource : Laravel singularise
        // « manches » en « manch » pour nommer le parametre, la liaison
        // automatique ne trouvait donc jamais l'objet. La suppression repondait
        // « Manche supprimee » sans rien supprimer — un succes qui ment.
        Route::post('manches', [MancheController::class, 'store']);
        Route::put('manches/{manche}', [MancheController::class, 'update']);
        Route::delete('manches/{manche}', [MancheController::class, 'destroy']);

        Route::get('preparation', [PreparationController::class, 'index']);
        Route::get('phases/etat', [PhaseController::class, 'etat']);
        Route::post('phases/generer', [PhaseController::class, 'generer']);

        Route::post('simulation', [SimulationController::class, 'simuler']);
        Route::post('simulation/appliquer', [SimulationController::class, 'appliquer']);

        Route::apiResource('membres', MembreController::class)->except(['show']);

        Route::get('acces', [AccesController::class, 'index']);
        Route::get('acces/sessions', [AccesController::class, 'sessions']);
        Route::post('acces/{acces}/regenerer', [AccesController::class, 'regenerer']);
        Route::put('acces/{acces}', [AccesController::class, 'definir']);
    });
});
