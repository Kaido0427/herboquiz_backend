<?php

namespace Tests\Feature;

use App\Models\Equipe;
use App\Models\Manche;
use App\Models\Point;
use App\Models\SessionAcces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le classement en direct et le barrage : la partie ou de l'argent se decide.
 * On verifie la regle de departage (rapidite puis barrage) et surtout qu'un
 * barrage ne gonfle AUCUN total.
 */
class ClassementBarrageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * S'authentifier comme en prod : un vrai jeton Bearer. SessionAcces n'est
     * pas un Authenticatable, donc Sanctum::actingAs le refuse — mais le guard,
     * lui, renvoie le tokenable tel quel. On teste le vrai chemin.
     */
    private function connecter(): SessionAcces
    {
        $session = SessionAcces::create(['role' => 'admin', 'nom' => 'Kaido']);
        $jeton = $session->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $jeton);

        return $session;
    }

    private function equipe(string $nom): Equipe
    {
        return Equipe::create(['nom' => $nom, 'active' => true]);
    }

    private function manche(array $equipes): Manche
    {
        $m = Manche::create([
            'libelle'            => 'Poule A',
            'type'               => 'poule',
            'nb_questions_prevu' => 12,
            'statut'             => 'en_cours',
            'question_courante'  => 0,
            'ordre'              => 1,
        ]);
        $m->equipes()->attach(collect($equipes)->pluck('id')->all());

        return $m;
    }

    /** Cree un point a un instant precis (pour maitriser la rapidite). */
    private function point(Manche $m, Equipe $e, int $points, string $instant, bool $departage = false): Point
    {
        $p = Point::create([
            'manche_id'     => $m->id,
            'equipe_id'     => $e->id,
            'points'        => $points,
            'est_departage' => $departage,
            'attribue_par'  => 'Testeur',
            'role_auteur'   => 'admin',
        ]);
        $p->created_at = $instant;
        $p->save();

        return $p;
    }

    public function test_a_points_egaux_le_plus_rapide_passe_devant(): void
    {
        $a = $this->equipe('Alpha');
        $b = $this->equipe('Beta');
        $m = $this->manche([$a, $b]);

        // Meme total (10), mais Alpha a atteint son total AVANT Beta.
        $this->point($m, $a, 10, '2026-07-27 18:00:01');
        $this->point($m, $b, 10, '2026-07-27 18:00:09');

        $classe = $m->classement();

        $this->assertSame($a->id, $classe[0]['equipe_id'], 'Le plus rapide doit etre premier');
        $this->assertSame($b->id, $classe[1]['equipe_id']);
        $this->assertTrue($classe[0]['ex_aequo']);
        $this->assertFalse($classe[0]['barrage_requis'], 'La rapidite tranche : pas de barrage');
    }

    public function test_egalite_parfaite_reclame_un_barrage(): void
    {
        $a = $this->equipe('Alpha');
        $b = $this->equipe('Beta');
        $m = $this->manche([$a, $b]);

        // Meme total ET meme instant : la rapidite ne departage plus.
        $this->point($m, $a, 10, '2026-07-27 18:00:05');
        $this->point($m, $b, 10, '2026-07-27 18:00:05');

        $classe = $m->classement();

        $this->assertTrue($classe[0]['barrage_requis']);
        $this->assertTrue($classe[1]['barrage_requis']);
    }

    public function test_le_barrage_designe_le_vainqueur_sans_changer_les_totaux(): void
    {
        $a = $this->equipe('Alpha');
        $b = $this->equipe('Beta');
        $m = $this->manche([$a, $b]);
        $this->point($m, $a, 10, '2026-07-27 18:00:05');
        $this->point($m, $b, 10, '2026-07-27 18:00:05');

        $this->connecter();

        // Beta gagne le barrage.
        $this->postJson("/api/manches/{$m->id}/barrage", ['equipe_id' => $b->id])
            ->assertOk();

        $classe = $m->fresh()->load('equipes')->classement();

        $this->assertSame($b->id, $classe[0]['equipe_id'], 'Le vainqueur du barrage passe devant');
        $this->assertTrue($classe[0]['departage']);
        $this->assertFalse($classe[0]['barrage_requis'], 'Egalite tranchee');
        // Le total ne bouge pas : un barrage n'est pas un point.
        $this->assertSame(10, $classe[0]['points']);
        $this->assertSame(10, $classe[1]['points']);
    }

    public function test_retirer_le_barrage_ramene_l_egalite(): void
    {
        $a = $this->equipe('Alpha');
        $b = $this->equipe('Beta');
        $m = $this->manche([$a, $b]);
        $this->point($m, $a, 10, '2026-07-27 18:00:05');
        $this->point($m, $b, 10, '2026-07-27 18:00:05');

        $this->connecter();

        $this->postJson("/api/manches/{$m->id}/barrage", ['equipe_id' => $b->id])->assertOk();
        $this->postJson("/api/manches/{$m->id}/barrage", ['equipe_id' => null])->assertOk();

        $classe = $m->fresh()->load('equipes')->classement();
        $this->assertTrue($classe[0]['barrage_requis'], 'Sans barrage, l\'egalite parfaite revient');
    }

    public function test_annuler_le_dernier_point_ignore_le_barrage(): void
    {
        $a = $this->equipe('Alpha');
        $b = $this->equipe('Beta');
        $m = $this->manche([$a, $b]);
        $this->point($m, $a, 10, '2026-07-27 18:00:05');
        $this->point($m, $b, 10, '2026-07-27 18:00:05');

        $this->connecter();
        $this->postJson("/api/manches/{$m->id}/barrage", ['equipe_id' => $b->id])->assertOk();

        // « Annuler le dernier point » doit retirer un VRAI point (celui de Beta),
        // jamais le barrage.
        $this->postJson("/api/manches/{$m->id}/annuler")->assertOk();

        $barrageActif = Point::where('manche_id', $m->id)
            ->where('est_departage', true)->whereNull('annule_le')->count();
        $this->assertSame(1, $barrageActif, 'Le barrage doit survivre a l\'annulation d\'un point');

        $pointBetaActif = Point::where('manche_id', $m->id)->where('equipe_id', $b->id)
            ->where('est_departage', false)->whereNull('annule_le')->count();
        $this->assertSame(0, $pointBetaActif, 'Le vrai point de Beta doit avoir ete annule');
    }

    public function test_le_barrage_ne_gonfle_pas_le_meilleur_marqueur(): void
    {
        $a = $this->equipe('Alpha');
        $b = $this->equipe('Beta');
        $m = $this->manche([$a, $b]);
        $this->point($m, $a, 10, '2026-07-27 18:00:05');
        $this->point($m, $b, 10, '2026-07-27 18:00:05');

        $this->connecter();
        $this->postJson("/api/manches/{$m->id}/barrage", ['equipe_id' => $b->id])->assertOk();

        // La page publique calcule le meilleur marqueur sur les poules : le
        // vainqueur du barrage ne doit pas y apparaitre avec plus de points.
        $data = $this->getJson('/api/public')->assertOk()->json();
        $this->assertSame(10, $data['meilleur_marqueur']['points']);
    }
}
