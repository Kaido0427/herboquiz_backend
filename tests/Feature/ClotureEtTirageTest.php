<?php

namespace Tests\Feature;

use App\Models\Equipe;
use App\Models\Manche;
use App\Models\Participant;
use App\Models\Point;
use App\Models\Poule;
use App\Models\Reglage;
use App\Models\SessionAcces;
use App\Support\Inscriptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClotureEtTirageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): void
    {
        $s = SessionAcces::create(['role' => 'admin', 'nom' => 'Kaido']);
        $this->withHeader('Authorization', 'Bearer ' . $s->createToken('t')->plainTextToken);
    }

    private function reglage(string $cle, string $valeur, string $type = 'texte'): void
    {
        Reglage::create([
            'cle' => $cle, 'valeur' => $valeur, 'type' => $type,
            'groupe' => 'inscriptions', 'libelle' => $cle, 'ordre' => 1,
        ]);
    }

    private function duo(string $a, string $b): Equipe
    {
        $e  = Equipe::create(['active' => true]);
        $p1 = Participant::create(['nom' => $a, 'confirme' => true]);
        $p2 = Participant::create(['nom' => $b, 'confirme' => true]);
        $e->participants()->sync([$p1->id, $p2->id]);

        return $e;
    }

    // ----- Cloture automatique ------------------------------------------------

    public function test_inscriptions_ouvertes_avant_l_heure(): void
    {
        config(['herboquiz.fuseau' => 'Africa/Porto-Novo']);
        $this->reglage('inscriptions.ouvertes', '1', 'booleen');
        $this->reglage('inscriptions.ferme_le', '2026-07-25 20:00');

        Carbon::setTestNow(Carbon::parse('2026-07-25 19:59', 'Africa/Porto-Novo'));
        $this->assertTrue(Inscriptions::ouvertes());
    }

    public function test_inscriptions_fermees_a_l_heure_pile(): void
    {
        config(['herboquiz.fuseau' => 'Africa/Porto-Novo']);
        $this->reglage('inscriptions.ouvertes', '1', 'booleen');
        $this->reglage('inscriptions.ferme_le', '2026-07-25 20:00');

        Carbon::setTestNow(Carbon::parse('2026-07-25 20:00', 'Africa/Porto-Novo'));
        $this->assertFalse(Inscriptions::ouvertes());
    }

    public function test_interrupteur_manuel_ferme_meme_avant_l_heure(): void
    {
        config(['herboquiz.fuseau' => 'Africa/Porto-Novo']);
        $this->reglage('inscriptions.ouvertes', '0', 'booleen');
        $this->reglage('inscriptions.ferme_le', '2026-07-25 20:00');

        Carbon::setTestNow(Carbon::parse('2026-07-25 10:00', 'Africa/Porto-Novo'));
        $this->assertFalse(Inscriptions::ouvertes());
    }

    public function test_inscrire_refuse_apres_la_cloture(): void
    {
        config(['herboquiz.fuseau' => 'Africa/Porto-Novo']);
        $this->reglage('inscriptions.ouvertes', '1', 'booleen');
        $this->reglage('inscriptions.ferme_le', '2026-07-25 20:00');

        Carbon::setTestNow(Carbon::parse('2026-07-25 21:00', 'Africa/Porto-Novo'));
        $this->postJson('/api/inscription', [
            'nom' => 'Tardif', 'email' => 't@t.co', 'telephone' => '90000000',
        ])->assertStatus(423);

        $this->assertSame(0, Participant::count());
    }

    public function test_public_expose_l_ouverture_calculee(): void
    {
        config(['herboquiz.fuseau' => 'Africa/Porto-Novo']);
        $this->reglage('inscriptions.ouvertes', '1', 'booleen');
        $this->reglage('inscriptions.ferme_le', '2026-07-25 20:00');

        Carbon::setTestNow(Carbon::parse('2026-07-25 21:00', 'Africa/Porto-Novo'));
        $data = $this->getJson('/api/public')->assertOk()->json();
        $this->assertFalse($data['reglages']['inscriptions.ouvertes']);
    }

    // ----- Simulation qui reflete la realite ----------------------------------

    public function test_simulation_reflete_les_equipes_reelles_en_duo(): void
    {
        $this->duo('Alpha', 'Beta');
        $this->duo('Gamma', 'Delta');
        $this->admin();

        $r = $this->postJson('/api/simulation', [])->assertOk()->json();

        $this->assertTrue($r['reel']);
        $this->assertSame('duo', $r['mode']);
        $this->assertSame(2, $r['concurrents']);   // 2 equipes, pas 4 participants
    }

    // ----- Re-tirage ----------------------------------------------------------

    public function test_retirer_repartit_a_nouveau_les_equipes(): void
    {
        $equipes = collect(range(1, 4))->map(fn ($i) => $this->duo("A$i", "B$i"));
        $poules = collect(['Poule A', 'Poule B'])->map(fn ($nom, $i) => Poule::create([
            'nom' => $nom, 'nb_qualifies' => 2, 'ordre' => $i + 1,
        ]));
        foreach ($poules as $p) {
            Manche::create(['libelle' => $p->nom, 'type' => 'poule', 'poule_id' => $p->id,
                'nb_questions_prevu' => 12, 'ordre' => $p->ordre]);
        }
        $this->admin();

        $this->postJson('/api/simulation/retirer')->assertOk();

        // Chaque equipe est dans exactement une poule, et la manche suit.
        $this->assertSame(4, \DB::table('equipe_poule')->count());
        $this->assertSame(4, \DB::table('equipe_manche')->count());
    }

    public function test_retirer_refuse_si_des_points_existent(): void
    {
        $e = $this->duo('Alpha', 'Beta');
        $poule = Poule::create(['nom' => 'Poule A', 'nb_qualifies' => 2, 'ordre' => 1]);
        $poule->equipes()->attach($e->id);
        $m = Manche::create(['libelle' => 'Poule A', 'type' => 'poule', 'poule_id' => $poule->id,
            'nb_questions_prevu' => 12, 'ordre' => 1]);
        $m->equipes()->attach($e->id);
        Point::create(['manche_id' => $m->id, 'equipe_id' => $e->id, 'points' => 10,
            'attribue_par' => 'Kaido', 'role_auteur' => 'admin']);
        $this->admin();

        $this->postJson('/api/simulation/retirer')->assertStatus(409);
    }

    public function test_retirer_refuse_sans_poules(): void
    {
        $this->duo('Alpha', 'Beta');
        $this->admin();

        $this->postJson('/api/simulation/retirer')->assertStatus(422);
    }

    public function test_reconstituer_les_equipes_remplit_les_poules_existantes(): void
    {
        // Des poules existent (vides) ; on reconstitue les equipes. Avant, elles
        // restaient vides et l'alerte « equipes non rattachees » revenait.
        foreach (['Deen', 'Ada', 'Bob', 'Cid'] as $nom) {
            Participant::create(['nom' => $nom, 'confirme' => true]);
        }
        foreach (['Poule A', 'Poule B'] as $i => $nom) {
            $p = Poule::create(['nom' => $nom, 'nb_qualifies' => 2, 'ordre' => $i + 1]);
            Manche::create(['libelle' => $nom, 'type' => 'poule', 'poule_id' => $p->id,
                'nb_questions_prevu' => 12, 'ordre' => $i + 1]);
        }
        $this->admin();

        $this->postJson('/api/equipes/generer', ['mode' => 'duo'])->assertOk();

        // 4 joueurs en duo -> 2 equipes, toutes deux rattachees a une poule.
        $this->assertSame(2, Equipe::count());
        $this->assertSame(2, \DB::table('equipe_poule')->count());
        $this->assertSame(2, \DB::table('equipe_manche')->count());
    }
}
