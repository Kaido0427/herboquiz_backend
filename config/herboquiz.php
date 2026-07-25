<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proprietaire du projet
    |--------------------------------------------------------------------------
    |
    | Le nom (declare a la connexion) du seul administrateur autorise a toucher
    | aux reglages « proprietaire » listes plus bas. Les six admins partagent un
    | code ; ce garde-fou reserve la signature « Propulse par NovafriQ » a Kaido,
    | pour qu'un autre admin ne puisse plus la retirer ou la remplacer.
    |
    | Rappel du modele : le nom est DECLARE, pas authentifie. C'est donc de
    | l'attribution, coherente avec le reste de l'app, pas un mur infranchissable.
    | Modifiable sans redeploiement via HERBOQUIZ_PROPRIETAIRE.
    |
    */
    'proprietaire' => env('HERBOQUIZ_PROPRIETAIRE', 'Kaido'),

    /*
    | Groupes de reglages reserves au proprietaire : les autres admins ne les
    | voient pas dans l'edition et le serveur refuse leurs modifications.
    | - signature : la mention « Propulse par NovafriQ » en pied de page.
    | - promo     : le bloc qui met en avant Gextimo/NovafriQ. C'est la
    |               contrepartie de l'infra offerte ; un autre admin ne doit pas
    |               pouvoir le retirer.
    */
    'groupes_proprietaire' => ['signature', 'promo'],

    /*
    |--------------------------------------------------------------------------
    | Fuseau horaire du tournoi
    |--------------------------------------------------------------------------
    |
    | L'app tourne en UTC, mais les horaires annonces (coup d'envoi, cloture
    | des inscriptions) sont a l'heure du Benin. C'est dans ce fuseau qu'on
    | interprete la date de fermeture automatique des inscriptions.
    |
    */
    'fuseau' => env('HERBOQUIZ_FUSEAU', 'Africa/Porto-Novo'),

];
