<?php

namespace Database\Seeders;

use App\Models\Reglage;
use Illuminate\Database\Seeder;

/**
 * Catalogue de TOUT ce qui doit rester modifiable depuis l'administration.
 *
 * Regle de fond du projet : aucun texte, aucun prix, aucun seuil ne vit dans le
 * code. Si une valeur doit un jour changer sans redeploiement, sa place est
 * ici. Le seeder ne fait que POSER les valeurs de depart : il n'ecrase jamais
 * une valeur deja modifiee par un administrateur (updateOrCreate sur la cle,
 * mais la valeur n'est ecrite qu'a la creation).
 */
class ReglagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogue() as $ordre => $r) {
            $existe = Reglage::where('cle', $r['cle'])->first();

            if ($existe) {
                // On rafraichit le libelle et l'aide (ils peuvent s'ameliorer),
                // mais JAMAIS la valeur : elle appartient a l'administrateur.
                $existe->update([
                    'libelle' => $r['libelle'],
                    'aide'    => $r['aide']   ?? null,
                    'groupe'  => $r['groupe'],
                    'type'    => $r['type'],
                    'ordre'   => $ordre,
                ]);

                continue;
            }

            Reglage::create($r + ['ordre' => $ordre]);
        }
    }

    private function catalogue(): array
    {
        return [
            // ---------- Identite de l'evenement ----------
            [
                'cle' => 'tournoi.nom', 'groupe' => 'general', 'type' => 'texte',
                'libelle' => 'Nom du tournoi',
                'valeur'  => 'Tournoi TEAM DES HERBOGENISTES 2026',
            ],
            [
                'cle' => 'tournoi.organisateur', 'groupe' => 'general', 'type' => 'texte',
                'libelle' => 'Organisateur',
                'valeur'  => 'TEAM DES HERBOGENISTES',
            ],
            [
                'cle' => 'tournoi.debut', 'groupe' => 'general', 'type' => 'texte',
                'libelle' => 'Date et heure du coup d\'envoi',
                'aide'    => 'Affiche tel quel sur la page publique. Heure du Benin.',
                'valeur'  => 'Lundi 27 juillet 2026 a 18h00',
            ],
            [
                'cle' => 'tournoi.canal_poules', 'groupe' => 'general', 'type' => 'texte',
                'libelle' => 'Canal des matchs de poules',
                'valeur'  => 'Groupe Messenger',
            ],
            [
                'cle' => 'tournoi.canal_finales', 'groupe' => 'general', 'type' => 'texte',
                'libelle' => 'Canal des phases finales',
                'valeur'  => 'Groupe WhatsApp',
            ],

            // ---------- Inscriptions ----------
            [
                'cle' => 'inscriptions.ouvertes', 'groupe' => 'inscriptions', 'type' => 'booleen',
                'libelle' => 'Inscriptions ouvertes',
                'aide'    => 'Une fois ferme, la page publique affiche la liste definitive.',
                'valeur'  => '1',
            ],
            [
                'cle' => 'inscriptions.comment', 'groupe' => 'inscriptions', 'type' => 'markdown',
                'libelle' => 'Comment s\'inscrire',
                'aide'    => 'Le point le plus important de l\'annonce : sans methode claire, personne ne s\'inscrit.',
                'valeur'  => "Envoyez votre nom et prenom dans le groupe, ou par message prive a un administrateur.",
            ],
            [
                'cle' => 'inscriptions.date_limite', 'groupe' => 'inscriptions', 'type' => 'texte',
                'libelle' => 'Date limite d\'inscription',
                'aide'    => 'Sans date de cloture, impossible de figer le format ni de composer les poules.',
                'valeur'  => 'Dimanche 26 juillet 2026 a 20h00',
            ],

            // ---------- Prix ----------
            [
                'cle' => 'prix.premier', 'groupe' => 'prix', 'type' => 'nombre',
                'libelle' => 'Prix du 1er (FCFA)', 'valeur' => '10000',
            ],
            [
                'cle' => 'prix.deuxieme', 'groupe' => 'prix', 'type' => 'nombre',
                'libelle' => 'Prix du 2e (FCFA)', 'valeur' => '5000',
            ],
            [
                'cle' => 'prix.troisieme', 'groupe' => 'prix', 'type' => 'nombre',
                'libelle' => 'Prix du 3e (FCFA)',
                'aide'    => 'Suppose un match pour la 3e place entre les deux perdants des demi-finales.',
                'valeur'  => '2000',
            ],
            [
                'cle' => 'prix.animateurs', 'groupe' => 'prix', 'type' => 'nombre',
                'libelle' => 'Enveloppe des animateurs (FCFA)', 'valeur' => '8000',
            ],
            [
                'cle' => 'prix.devise', 'groupe' => 'prix', 'type' => 'texte',
                'libelle' => 'Devise affichee', 'valeur' => 'FCFA',
            ],
            [
                'cle' => 'prix.versement', 'groupe' => 'prix', 'type' => 'markdown',
                'libelle' => 'Modalites de versement',
                'valeur'  => "Les prix sont verses par Mobile Money dans les jours qui suivent la finale.",
            ],

            // ---------- Textes publics ----------
            [
                'cle' => 'textes.annonce', 'groupe' => 'annonce', 'type' => 'markdown',
                'libelle' => 'Annonce officielle',
                'aide'    => 'Texte principal de la page d\'accueil.',
                'valeur'  => "Le moment tant attendu est arrive : le tournoi de la TEAM DES HERBOGENISTES ouvre ses portes.\n\nLes matchs de poules se jouent dans le groupe Messenger, les phases finales sur WhatsApp. Que chacun vienne defendre ses connaissances dans le respect du fair-play.",
            ],
            [
                'cle' => 'textes.reglement', 'groupe' => 'reglement', 'type' => 'markdown',
                'libelle' => 'Reglement du tournoi',
                'aide'    => 'Reste court : un reglement long n\'est pas lu. N\'y mettez que des regles applicables.',
                'valeur'  => "1. L'animateur pose la question, puis annonce STOP. Toute reponse posterieure est nulle.\n2. La premiere bonne reponse marque le point. Recopier une reponse deja donnee ne rapporte rien.\n3. La rapidite fait partie du jeu.\n4. L'orthographe approximative est acceptee tant que la reponse est reconnaissable.\n5. Une seule participation par personne.\n6. En cas de litige, la decision de l'animateur est definitive.\n7. Une absence a une manche ne donne pas droit a un rattrapage.",
            ],
            [
                'cle' => 'textes.pied_page', 'groupe' => 'annonce', 'type' => 'texte',
                'libelle' => 'Mention de pied de page',
                'valeur'  => 'Le savoir est notre force, l\'excellence est notre objectif.',
            ],

            // ---------- Regles de jeu (parametres, jamais des constantes) ----------
            [
                'cle' => 'jeu.points_bonne_reponse', 'groupe' => 'jeu', 'type' => 'nombre',
                'libelle' => 'Points par bonne reponse',
                'aide'    => 'Habitude du groupe : 10 points. Les seuils de duel s\'expriment en bonnes reponses, ils suivent donc automatiquement.',
                'valeur'  => '10',
            ],
            [
                'cle' => 'jeu.score_cible_duel', 'groupe' => 'jeu', 'type' => 'nombre',
                'libelle' => 'Bonnes reponses pour gagner un duel',
                'aide'    => 'Exprime en BONNES REPONSES, pas en points : changer la valeur d\'une bonne reponse ne dereglera pas les duels.',
                'valeur'  => '5',
            ],
            [
                'cle' => 'jeu.score_cible_finale', 'groupe' => 'jeu', 'type' => 'nombre',
                'libelle' => 'Bonnes reponses pour gagner la finale',
                'aide'    => 'Plus eleve que les autres duels : cela donne du poids au titre.',
                'valeur'  => '7',
            ],

            [
                'cle' => 'jeu.jours_entre_tours', 'groupe' => 'jeu', 'type' => 'nombre',
                'libelle' => 'Jours entre deux tours',
                'aide'    => 'Sert a proposer les dates des tours suivants. Chaque date reste modifiable.',
                'valeur'  => '1',
            ],
            [
                'cle' => 'jeu.heure_manche', 'groupe' => 'jeu', 'type' => 'texte',
                'libelle' => 'Heure habituelle des manches',
                'aide'    => 'Format 24h, par exemple 18:00. Heure du Benin.',
                'valeur'  => '18:00',
            ],

            // ---------- Seuils de la simulation ----------
            [
                'cle' => 'simulation.max_par_poule', 'groupe' => 'simulation', 'type' => 'nombre',
                'libelle' => 'Joueurs maximum par poule',
                'aide'    => 'Au-dela, les reponses defilent trop vite dans le groupe pour que l\'animateur suive.',
                'valeur'  => '20',
            ],
            [
                'cle' => 'simulation.qualifies_par_poule', 'groupe' => 'simulation', 'type' => 'nombre',
                'libelle' => 'Qualifies par poule', 'valeur' => '4',
            ],
            [
                'cle' => 'simulation.questions_par_joueur', 'groupe' => 'simulation', 'type' => 'nombre',
                'libelle' => 'Questions par joueur',
                'aide'    => 'Sert a proposer le nombre de questions d\'une manche de poule.',
                'valeur'  => '1',
            ],
            [
                'cle' => 'simulation.questions_min', 'groupe' => 'simulation', 'type' => 'nombre',
                'libelle' => 'Questions minimum par manche', 'valeur' => '12',
            ],
            [
                'cle' => 'simulation.questions_max', 'groupe' => 'simulation', 'type' => 'nombre',
                'libelle' => 'Questions maximum par manche',
                'aide'    => 'Au-dela, l\'attention retombe et le forfait data des joueurs y passe.',
                'valeur'  => '20',
            ],
            [
                'cle' => 'simulation.seuil_sans_poules', 'groupe' => 'simulation', 'type' => 'nombre',
                'libelle' => 'Effectif sous lequel on saute les poules',
                'aide'    => 'En dessous, on va directement au tableau final.',
                'valeur'  => '16',
            ],
            [
                'cle' => 'simulation.seuil_duo', 'groupe' => 'simulation', 'type' => 'nombre',
                'libelle' => 'Effectif a partir duquel le duo est conseille',
                'aide'    => 'Le duo divise par deux le nombre de repondants et fait jouer les faibles avec les forts.',
                'valeur'  => '25',
            ],
        ];
    }
}
