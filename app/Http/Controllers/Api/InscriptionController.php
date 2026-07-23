<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Reglage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Inscription en autonomie.
 *
 * Deux populations arrivent ici :
 *  - ceux qu'un administrateur a deja saisis a la main, avec le seul nom. Leur
 *    fiche existe donc, incomplete. Les faire s'inscrire normalement creerait
 *    un doublon et fausserait le dimensionnement du format.
 *  - les nouveaux, qui remplissent tout.
 *
 * D'ou la verification prealable par le nom : on retrouve la fiche existante et
 * on ne demande que ce qui manque.
 *
 * C'est une route PUBLIQUE, donc exposee. Les garde-fous ne sont pas
 * optionnels : sans eux, cent inscriptions envoyees en une minute fausseraient
 * le tournoi avant meme qu'il commence.
 */
class InscriptionController extends Controller
{
    /** Cette personne a-t-elle deja une fiche ? Si oui, que manque-t-il ? */
    public function verifier(Request $request)
    {
        $data = $request->validate([
            'nom'    => ['required', 'string', 'max:80'],
            'prenom' => ['nullable', 'string', 'max:80'],
        ]);

        $cible = Participant::normaliser(trim(($data['prenom'] ?? '') . ' ' . $data['nom']));

        foreach (Participant::all() as $p) {
            $complet = Participant::normaliser($p->prenom . ' ' . $p->nom);
            $seulNom = Participant::normaliser($p->nom);

            if ($cible === $complet || $cible === $seulNom) {
                return response()->json([
                    'existe'   => true,
                    'id'       => $p->id,
                    'nom'      => $p->nom_affiche,
                    // On ne renvoie pas les valeurs, seulement ce qui manque :
                    // la route est publique, inutile d'exposer un numero.
                    'manquant' => array_values(array_filter([
                        $p->email ? null : 'email',
                        $p->telephone ? null : 'telephone',
                        $p->lien_facebook ? null : 'lien_facebook',
                        $p->pseudo ? null : 'pseudo',
                    ])),
                ]);
            }
        }

        return response()->json(['existe' => false]);
    }

    public function inscrire(Request $request)
    {
        if (! Reglage::valeur('inscriptions.ouvertes', true)) {
            return response()->json(['message' => 'Les inscriptions sont fermees.'], 423);
        }

        $data = $request->validate([
            'nom'           => ['required', 'string', 'max:80'],
            'prenom'        => ['nullable', 'string', 'max:80'],
            'pseudo'        => ['nullable', 'string', 'max:80'],
            'email'         => ['required', 'email', 'max:120'],
            'telephone'     => ['required', 'string', 'max:30'],
            'lien_facebook' => ['nullable', 'string', 'max:200'],
        ]);

        $tel = preg_replace('/\D+/', '', $data['telephone']);

        // Rapprochement avec une fiche existante : par le nom si elle vient d'un
        // administrateur, par l'e-mail ou le numero si la personne revient.
        $existant = Participant::where('email', $data['email'])->first()
            ?? Participant::all()->first(fn ($p) => $p->telephone && preg_replace('/\D+/', '', $p->telephone) === $tel)
            ?? Participant::all()->first(function ($p) use ($data) {
                $cible = Participant::normaliser(trim(($data['prenom'] ?? '') . ' ' . $data['nom']));

                return $cible === Participant::normaliser($p->prenom . ' ' . $p->nom)
                    || $cible === Participant::normaliser($p->nom);
            });

        if ($existant) {
            // Deja inscrit PAR LUI-MEME : c'est un doublon, on refuse.
            if ($existant->auto_inscrit) {
                return response()->json([
                    'message' => 'Vous etes deja inscrit.',
                    'deja'    => true,
                ], 409);
            }

            // Fiche posee par un administrateur : on la complete au lieu d'en
            // creer une seconde.
            $existant->update([
                'prenom'        => $existant->prenom ?: ($data['prenom'] ?? null),
                'pseudo'        => $existant->pseudo ?: ($data['pseudo'] ?? null),
                'email'         => $data['email'],
                'telephone'     => $data['telephone'],
                'lien_facebook' => $data['lien_facebook'] ?? null,
                'auto_inscrit'  => true,
                'inscrit_le'    => now(),
                'confirme'      => true,
            ]);

            return response()->json([
                'message'  => 'Votre inscription est confirmee.',
                'complete' => true,
                'nom'      => $existant->nom_affiche,
            ]);
        }

        $p = Participant::create($data + [
            'auto_inscrit' => true,
            'inscrit_le'   => now(),
            'confirme'     => true,
        ]);

        return response()->json([
            'message' => 'Votre inscription est enregistree.',
            'nom'     => $p->nom_affiche,
        ], 201);
    }
}
