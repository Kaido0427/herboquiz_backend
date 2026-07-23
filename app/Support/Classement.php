<?php

namespace App\Support;

use App\Models\Point;
use Illuminate\Support\Collection;

/**
 * La regle de classement du tournoi, ecrite UNE seule fois.
 *
 * Avant, deux classements coexistaient avec deux regles differentes : celui de
 * la manche en direct (tri par points seuls) et celui de la qualification (tri
 * par points PUIS rapidite). Un meme jeu de points pouvait donc donner un ordre
 * a l'ecran et un autre a la generation du tour. Tout passe desormais par ici.
 *
 * L'ordre, dans cet ordre exact :
 *   1. le plus de points d'abord ;
 *   2. a points egaux, le plus RAPIDE (celui qui a atteint son total en
 *      premier — le plus petit « dernier point ») ;
 *   3. a rapidite egale (egalite PARFAITE), le vainqueur du barrage.
 *
 * Un barrage est une ligne du journal a points = 0 (`est_departage`) : il ne
 * change aucun total, il ne fait que placer son beneficiaire devant a total et
 * rapidite egaux. Tant qu'aucun barrage n'a tranche une egalite parfaite, les
 * equipes concernees sont marquees `barrage_requis`.
 */
class Classement
{
    /**
     * @param  Collection  $equipes    Les equipes a classer (celles a 0 point comprises).
     * @param  array|Collection  $mancheIds  Les manches dont on somme les points.
     * @return array  Lignes triees, chacune enrichie de rang et drapeaux d'egalite.
     */
    public static function pour(Collection $equipes, $mancheIds): array
    {
        $mancheIds = collect($mancheIds)->all();

        // Une seule requete. Portable SQLite / MySQL / PostgreSQL : on evite
        // BOOL_OR et « = false » (pieges Postgres) au profit de CASE WHEN, qui
        // se comporte pareil partout. `dernier` ignore les lignes de barrage :
        // sinon leur horodatage tardif ferait passer le vainqueur pour le plus
        // LENT, soit l'inverse de l'effet voulu.
        $stats = Point::whereIn('manche_id', $mancheIds)
            ->whereNull('annule_le')
            ->groupBy('equipe_id')
            ->selectRaw('equipe_id')
            ->selectRaw('SUM(points) AS total')
            ->selectRaw('MAX(CASE WHEN est_departage THEN NULL ELSE created_at END) AS dernier')
            ->selectRaw('MAX(CASE WHEN est_departage THEN 1 ELSE 0 END) AS a_barrage')
            ->get()
            ->keyBy('equipe_id');

        $lignes = $equipes->map(function ($e) use ($stats) {
            $s = $stats->get($e->id);

            return [
                'equipe_id' => $e->id,
                'libelle'   => $e->libelle,
                'points'    => (int) ($s->total ?? 0),
                'dernier'   => $s->dernier ?? null,
                'departage' => (bool) ($s->a_barrage ?? false),
            ];
        })->values()->all();

        usort($lignes, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];          // plus de points devant
            }
            // Null (aucun point marque) = le plus lent possible.
            $da = $a['dernier'] ?? '9999-99-99 99:99:99';
            $db = $b['dernier'] ?? '9999-99-99 99:99:99';
            if ($da !== $db) {
                return $da <=> $db;                            // le plus rapide devant
            }

            return ($b['departage'] <=> $a['departage']);      // vainqueur du barrage devant
        });

        return self::marquer($lignes);
    }

    /**
     * Pose le rang (partage en cas d'egalite de points) et les drapeaux
     * d'egalite pour l'affichage.
     */
    private static function marquer(array $lignes): array
    {
        // Groupes d'egalite PARFAITE (memes points ET meme rapidite) : c'est la
        // que la rapidite ne departage plus. Un groupe est resolu quand il
        // reste au plus une equipe sans barrage (il faut n-1 barrages pour
        // ordonner n equipes parfaitement ex aequo).
        $parfait = [];
        foreach ($lignes as $i => $l) {
            $cle = $l['points'] . '|' . ($l['dernier'] ?? '');
            $parfait[$cle][] = $i;
        }

        foreach ($lignes as $i => &$l) {
            // Rang par points : les equipes a total egal partagent le rang.
            $devant = 0;
            foreach ($lignes as $autre) {
                if ($autre['points'] > $l['points']) {
                    $devant++;
                }
            }
            $l['rang'] = $devant + 1;

            // Ex aequo : au moins une autre equipe au meme total. On ignore le
            // 0 : en debut de manche tout le monde y est, le signaler n'apprend
            // rien et encombre l'ecran.
            $l['ex_aequo'] = $l['points'] > 0 && collect($lignes)
                ->where('points', $l['points'])
                ->count() > 1;

            // Barrage requis : egalite parfaite pas encore tranchee (et pas a 0).
            $groupe = $parfait[$l['points'] . '|' . ($l['dernier'] ?? '')];
            $sansBarrage = collect($groupe)->filter(fn ($j) => ! $lignes[$j]['departage'])->count();
            $l['barrage_requis'] = $l['points'] > 0 && count($groupe) >= 2 && $sansBarrage >= 2;
        }
        unset($l);

        return $lignes;
    }
}
