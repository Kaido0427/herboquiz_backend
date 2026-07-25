<?php

namespace Tests\Feature;

use App\Models\Equipe;
use App\Models\Manche;
use App\Models\Point;
use App\Models\Poule;
use App\Models\SessionAcces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EliminationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $s = SessionAcces::create(['role' => 'admin', 'nom' => 'Kaido']);
        $this->withHeader('Authorization', 'Bearer ' . $s->createToken('t')->plainTextToken);
    }

    private function point(Manche $m, Equipe $e, int $pts, string $instant): void
    {
        $p = Point::create(['manche_id' => $m->id, 'equipe_id' => $e->id, 'points' => $pts,
            'attribue_par' => 'Kaido', 'role_auteur' => 'admin']);
        $p->created_at = $instant;
        $p->save();
    }

    public function test_le_bouton_bascule_elimine_puis_reintegre(): void
    {
        $e = Equipe::create(['nom' => 'Titus', 'active' => true]);
        $this->admin();

        $this->postJson("/api/equipes/{$e->id}/eliminer")->assertOk()->assertJson(['elimine' => true]);
        $this->assertNotNull($e->fresh()->elimine_le);

        $this->postJson("/api/equipes/{$e->id}/eliminer")->assertOk()->assertJson(['elimine' => false]);
        $this->assertNull($e->fresh()->elimine_le);
    }

    public function test_une_eliminee_reste_au_classement_mais_barree(): void
    {
        $e = Equipe::create(['nom' => 'Titus', 'active' => true, 'elimine_le' => now()]);
        $m = Manche::create(['libelle' => 'M', 'type' => 'poule', 'nb_questions_prevu' => 12, 'ordre' => 1]);
        Point::create(['manche_id' => $m->id, 'equipe_id' => $e->id, 'points' => 10,
            'attribue_par' => 'Kaido', 'role_auteur' => 'admin']);

        $data = $this->getJson('/api/public')->assertOk()->json();
        $ligne = collect($data['classement'])->firstWhere('libelle', $e->libelle);

        $this->assertNotNull($ligne, 'L\'eliminee reste visible au classement general');
        $this->assertTrue($ligne['elimine']);
    }

    public function test_une_eliminee_ne_se_qualifie_pas(): void
    {
        $a = Equipe::create(['nom' => 'Alpha', 'active' => true]);
        $b = Equipe::create(['nom' => 'Beta', 'active' => true]);
        $c = Equipe::create(['nom' => 'Gamma', 'active' => true]);

        $poule = Poule::create(['nom' => 'Poule A', 'nb_qualifies' => 2, 'ordre' => 1]);
        $poule->equipes()->attach([$a->id, $b->id, $c->id]);

        $m = Manche::create(['libelle' => 'Poule A', 'type' => 'poule', 'poule_id' => $poule->id,
            'nb_questions_prevu' => 12, 'statut' => 'terminee', 'question_courante' => 12, 'ordre' => 1]);
        $m->equipes()->attach([$a->id, $b->id, $c->id]);

        // Classement : Alpha 30, Beta 20, Gamma 10.
        $this->point($m, $a, 30, '2026-07-27 18:00:01');
        $this->point($m, $b, 20, '2026-07-27 18:00:02');
        $this->point($m, $c, 10, '2026-07-27 18:00:03');

        // Normalement Alpha + Beta se qualifient. On elimine Alpha.
        $a->update(['elimine_le' => now()]);

        $this->admin();
        $this->postJson('/api/phases/generer')->assertOk();

        // Le tour genere oppose Beta et Gamma ; Alpha (eliminee) en est absente.
        $equipesEnLice = Manche::where('type', 'duel')->get()
            ->flatMap(fn ($d) => $d->equipes()->pluck('equipes.id'))->unique()->values();

        $this->assertContains($b->id, $equipesEnLice);
        $this->assertContains($c->id, $equipesEnLice);
        $this->assertNotContains($a->id, $equipesEnLice, 'Une equipe eliminee ne doit pas etre qualifiee');
    }
}
