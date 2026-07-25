<?php

namespace App\Support;

use App\Models\Reglage;
use Illuminate\Support\Carbon;

/**
 * Les inscriptions sont-elles ouvertes ?
 *
 * Deux verrous, dans cet ordre :
 *   1. l'interrupteur manuel `inscriptions.ouvertes` (l'admin peut fermer avant
 *      l'heure) ;
 *   2. la fermeture AUTOMATIQUE a `inscriptions.ferme_le` (heure du Benin).
 *
 * On calcule a la lecture plutot que via une tache planifiee : pas de cron a
 * surveiller, pas de derive d'horloge, et c'est juste a la seconde pres. A
 * 20h00 pile, `inscrire` refuse et la page publique masque le formulaire, sans
 * qu'aucune tache n'ait eu a s'executer.
 */
class Inscriptions
{
    public static function ouvertes(): bool
    {
        if (! Reglage::valeur('inscriptions.ouvertes', true)) {
            return false;                       // ferme a la main : c'est ferme
        }

        $ferme = Reglage::valeur('inscriptions.ferme_le');
        if (! $ferme) {
            return true;                        // pas de date : seul l'interrupteur compte
        }

        try {
            $limite = Carbon::parse($ferme, config('herboquiz.fuseau', 'Africa/Porto-Novo'));
        } catch (\Throwable) {
            return true;                        // date illisible : ne jamais fermer par erreur
        }

        return Carbon::now()->lt($limite);
    }
}
