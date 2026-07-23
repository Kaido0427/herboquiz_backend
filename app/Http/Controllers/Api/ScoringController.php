<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manche;
use App\Models\Point;
use App\Models\Reglage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Le coeur de l'application : attribuer un point pendant un match en direct.
 *
 * Tout est dimensionne pour UN SEUL geste sur un telephone, pendant que le
 * groupe attend. Le reste (classement, historique, annulation) se deduit du
 * journal des points, jamais d'un total stocke qu'il faudrait maintenir.
 */
class ScoringController extends Controller
{
    /** Vue de travail de l'animateur : la manche, sa question courante, le classement. */
    public function manche(Manche $manche)
    {
        $manche->load(['equipes.participants', 'questions']);

        $questions = $manche->questions;
        $index     = $manche->question_courante;

        return response()->json([
            'manche' => [
                'id'                 => $manche->id,
                'libelle'            => $manche->libelle,
                'type'               => $manche->type,
                'phase'              => $manche->phase,
                'statut'             => $manche->statut,
                'nb_questions_prevu' => $manche->nb_questions_prevu,
                'score_cible'        => $manche->score_cible,
                'question_courante'  => $index,
                'nb_questions'       => $questions->count(),
            ],
            'question' => $questions[$index] ?? null,
            'equipes'  => $manche->equipes->map(fn ($e) => [
                'id'      => $e->id,
                'libelle' => $e->libelle,
            ])->values(),
            'classement' => $manche->classement(),
            'dernier'    => $this->dernierPoint($manche),
        ]);
    }

    /**
     * Attribuer le point de la question courante.
     *
     * `equipe_id` absent = personne n'a trouve : on avance sans marquer. C'est
     * un cas frequent et il ne doit pas obliger l'animateur a sortir de l'ecran.
     */
    public function attribuer(Request $request, Manche $manche)
    {
        $data = $request->validate([
            'equipe_id'   => ['nullable', 'uuid', 'exists:equipes,id'],
            'question_id' => ['nullable', 'uuid', 'exists:questions,id'],
        ]);

        // L'equipe doit REELLEMENT jouer cette manche. Sans ce controle, un
        // point partait dans le vide : il n'apparaissait pas au classement de
        // la manche (qui ne parcourt que ses equipes) mais comptait quand meme
        // au classement general affiche publiquement. Un ecart inexplicable
        // entre les deux, impossible a diagnostiquer un soir de match.
        if (! empty($data['equipe_id'])
            && ! $manche->equipes()->whereKey($data['equipe_id'])->exists()) {
            return response()->json([
                'message' => 'Cette equipe ne participe pas a cette manche.',
            ], 422);
        }

        $session = $request->user();

        $point = DB::transaction(function () use ($manche, $data, $session) {
            $point = null;

            if (! empty($data['equipe_id'])) {
                $point = Point::create([
                    'manche_id'    => $manche->id,
                    'question_id'  => $data['question_id'] ?? null,
                    'equipe_id'    => $data['equipe_id'],
                    'points'       => (int) Reglage::valeur('jeu.points_bonne_reponse', 1),
                    'attribue_par' => $session->nom,
                    'role_auteur'  => $session->role,
                ]);
            }

            // On avance d'une question dans tous les cas, y compris quand
            // personne n'a trouve.
            $manche->increment('question_courante');

            if ($manche->statut === 'a_venir') {
                $manche->update(['statut' => 'en_cours']);
            }

            // Cloture automatique quand toutes les questions prevues sont
            // passees. Sinon l'animateur doit penser a cliquer « terminer », et
            // s'il l'oublie la generation du tour suivant reste bloquee sans
            // que personne ne comprenne pourquoi.
            if ($manche->type === 'poule'
                && $manche->nb_questions_prevu > 0
                && $manche->question_courante >= $manche->nb_questions_prevu) {
                $manche->update(['statut' => 'terminee']);
            }

            return $point;
        });

        $session->update(['derniere_activite' => now()]);
        $manche->refresh()->load('equipes.participants');

        return response()->json([
            'point'             => $point,
            'classement'        => $manche->classement(),
            'question_courante' => $manche->question_courante,
            'termine'           => $this->estTerminee($manche),
        ]);
    }

    /**
     * Annuler la derniere attribution.
     *
     * On n'efface pas la ligne : on la marque annulee, en gardant qui l'avait
     * donnee et qui l'a retiree. Un litige se tranche sur des faits.
     */
    public function annuler(Request $request, Manche $manche)
    {
        $session = $request->user();
        $point   = $this->dernierPoint($manche, brut: true);

        if (! $point) {
            return response()->json(['message' => 'Aucun point a annuler.'], 404);
        }

        DB::transaction(function () use ($point, $manche, $session) {
            $point->update(['annule_le' => now(), 'annule_par' => $session->nom]);

            if ($manche->question_courante > 0) {
                $manche->decrement('question_courante');
            }
        });

        $manche->refresh()->load('equipes.participants');

        return response()->json([
            'classement'        => $manche->classement(),
            'question_courante' => $manche->question_courante,
        ]);
    }

    public function terminer(Manche $manche)
    {
        $manche->update(['statut' => 'terminee']);

        return response()->json(['statut' => $manche->statut]);
    }

    /** Une manche close par erreur ne doit pas etre un cul-de-sac. */
    public function rouvrir(Manche $manche)
    {
        $manche->update(['statut' => 'en_cours']);

        return response()->json(['statut' => $manche->statut]);
    }

    /**
     * Trancher une egalite PARFAITE par un barrage.
     *
     * A total et rapidite egaux, la regle automatique ne departage plus.
     * L'animateur pose une question decisive dans le groupe et designe le
     * vainqueur ici. On l'enregistre comme une ligne a points = 0 : elle ne
     * gonfle aucun total (ni le classement, ni le prix du meilleur marqueur),
     * elle place seulement le vainqueur devant a total egal, et garde le nom de
     * qui a tranche. Un seul barrage actif par manche : re-designer remplace le
     * precedent sans l'effacer. `equipe_id` absent = on retire le barrage.
     */
    public function barrage(Request $request, Manche $manche)
    {
        $data = $request->validate([
            'equipe_id' => ['nullable', 'uuid', 'exists:equipes,id'],
        ]);

        if (! empty($data['equipe_id'])
            && ! $manche->equipes()->whereKey($data['equipe_id'])->exists()) {
            return response()->json([
                'message' => 'Cette equipe ne participe pas a cette manche.',
            ], 422);
        }

        $session = $request->user();

        DB::transaction(function () use ($manche, $data, $session) {
            $manche->points()->where('est_departage', true)->whereNull('annule_le')
                ->update(['annule_le' => now(), 'annule_par' => $session->nom]);

            if (! empty($data['equipe_id'])) {
                Point::create([
                    'manche_id'     => $manche->id,
                    'equipe_id'     => $data['equipe_id'],
                    'points'        => 0,
                    'est_departage' => true,
                    'attribue_par'  => $session->nom,
                    'role_auteur'   => $session->role,
                ]);
            }
        });

        $session->update(['derniere_activite' => now()]);
        $manche->refresh()->load('equipes.participants');

        return response()->json(['classement' => $manche->classement()]);
    }

    private function dernierPoint(Manche $manche, bool $brut = false)
    {
        // Depart sur created_at seul : deux points attribues dans la meme
        // seconde rendaient le choix ambigu, et on annulait parfois le mauvais.
        // On ignore les barrages : « annuler le dernier point » ne doit retirer
        // qu'un vrai point marque, jamais un departage (qui se retire depuis son
        // propre outil).
        $p = $manche->points()->whereNull('annule_le')
            ->where('est_departage', false)
            ->orderByDesc('created_at')->orderByDesc('id')->first();

        if (! $p || $brut) {
            return $p;
        }

        return ['id' => $p->id, 'equipe_id' => $p->equipe_id, 'attribue_par' => $p->attribue_par];
    }

    /** Un duel s'arrete des qu'une equipe atteint le score cible. */
    private function estTerminee(Manche $manche): bool
    {
        if ($manche->type === 'duel' && $manche->score_cible) {
            // score_cible est un nombre de BONNES REPONSES, pas de points.
            // Sans cette conversion, passer la bonne reponse a 10 points
            // terminerait un duel « premier a 5 » des la premiere question.
            $parReponse = max(1, (int) Reglage::valeur('jeu.points_bonne_reponse', 1));
            $seuil = $manche->score_cible * $parReponse;

            foreach ($manche->classement() as $ligne) {
                if ($ligne['points'] >= $seuil) {
                    return true;
                }
            }

            return false;
        }

        return $manche->nb_questions_prevu > 0
            && $manche->question_courante >= $manche->nb_questions_prevu;
    }
}
