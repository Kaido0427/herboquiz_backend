<?php

namespace Tests\Feature;

use App\Models\Reglage;
use App\Models\SessionAcces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les reglages « proprietaire » (signature NovafriQ, bloc promo Gextimo) sont
 * la contrepartie de l'infra offerte : un autre admin ne doit pas pouvoir les
 * retirer. Le code admin etant partage, le garde-fou tient sur le NOM declare.
 */
class ReglagesProprietaireTest extends TestCase
{
    use RefreshDatabase;

    private function connecterEnTant(string $nom): void
    {
        $s = SessionAcces::create(['role' => 'admin', 'nom' => $nom]);
        $this->withHeader('Authorization', 'Bearer ' . $s->createToken('t')->plainTextToken);
    }

    private function accesAdmin(string $code = 'ABCD1234'): \App\Models\Acces
    {
        // code_hash est NOT NULL : passer par definirCode (hash + clair) plutot
        // que create() avec le seul code_clair.
        $a = new \App\Models\Acces(['role' => 'admin']);
        $a->definirCode($code);

        return $a;
    }

    private function signature(): void
    {
        Reglage::create([
            'cle' => 'signature.texte', 'groupe' => 'signature', 'type' => 'texte',
            'libelle' => 'Signature', 'valeur' => 'Propulse par NovafriQ', 'ordre' => 1,
        ]);
    }

    public function test_un_autre_admin_ne_peut_pas_modifier_la_signature(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido', 'herboquiz.groupes_proprietaire' => ['signature']]);
        $this->signature();
        $this->connecterEnTant('Titus');

        $this->putJson('/api/reglages', ['reglages' => [
            ['cle' => 'signature.texte', 'valeur' => 'Team Herbogenistes'],
        ]])->assertStatus(403);

        // La valeur d'origine n'a pas bouge.
        $this->assertSame('Propulse par NovafriQ', Reglage::where('cle', 'signature.texte')->value('valeur'));
    }

    public function test_le_proprietaire_peut_modifier_la_signature(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido', 'herboquiz.groupes_proprietaire' => ['signature']]);
        $this->signature();
        $this->connecterEnTant('Kaido');

        $this->putJson('/api/reglages', ['reglages' => [
            ['cle' => 'signature.texte', 'valeur' => 'Propulse par NovafriQ !'],
        ]])->assertOk();

        $this->assertSame('Propulse par NovafriQ !', Reglage::where('cle', 'signature.texte')->value('valeur'));
    }

    public function test_un_lot_mixte_est_refuse_en_bloc_pour_un_autre_admin(): void
    {
        // Un reglage libre + un reglage reserve dans le meme envoi : tout est
        // refuse, sinon on contournerait le verrou en le noyant dans un lot.
        config(['herboquiz.proprietaire' => 'Kaido', 'herboquiz.groupes_proprietaire' => ['signature']]);
        $this->signature();
        Reglage::create([
            'cle' => 'tournoi.nom', 'groupe' => 'general', 'type' => 'texte',
            'libelle' => 'Nom', 'valeur' => 'Ancien', 'ordre' => 2,
        ]);
        $this->connecterEnTant('Titus');

        $this->putJson('/api/reglages', ['reglages' => [
            ['cle' => 'tournoi.nom', 'valeur' => 'Nouveau'],
            ['cle' => 'signature.texte', 'valeur' => 'Retire'],
        ]])->assertStatus(403);

        $this->assertSame('Ancien', Reglage::where('cle', 'tournoi.nom')->value('valeur'));
    }

    // Deux tests separes : dans un meme test, le guard met en cache le premier
    // utilisateur resolu, la seconde requete renverrait donc le mauvais nom.
    public function test_moi_marque_le_proprietaire(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido']);
        $this->connecterEnTant('Kaido');
        $this->getJson('/api/moi')->assertOk()->assertJson(['proprietaire' => true]);
    }

    public function test_moi_ne_marque_pas_un_autre_admin(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido']);
        $this->connecterEnTant('Titus');
        $this->getJson('/api/moi')->assertOk()->assertJson(['proprietaire' => false]);
    }

    public function test_eliminer_un_joueur_est_ouvert_a_tous_les_admins(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido']);
        $p = \App\Models\Participant::create(['nom' => 'Archer', 'confirme' => true]);
        $this->connecterEnTant('Titus');   // pas le proprietaire

        $this->postJson("/api/participants/{$p->id}/eliminer")
            ->assertOk()->assertJson(['elimine' => true]);

        $this->assertNotNull($p->fresh()->elimine_le);
        // Reversible : il reste en base.
        $this->assertNotNull(\App\Models\Participant::find($p->id));
    }

    public function test_supprimer_un_joueur_est_reserve_au_proprietaire(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido']);
        $p = \App\Models\Participant::create(['nom' => 'Spam', 'confirme' => true]);
        $this->connecterEnTant('Titus');

        $this->deleteJson("/api/participants/{$p->id}")->assertStatus(403);
        $this->assertNotNull(\App\Models\Participant::find($p->id), 'Le joueur ne doit pas etre supprime');
    }

    public function test_le_proprietaire_peut_supprimer_un_joueur(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido']);
        $p = \App\Models\Participant::create(['nom' => 'Spam', 'confirme' => true]);
        $this->connecterEnTant('Kaido');

        $this->deleteJson("/api/participants/{$p->id}")->assertOk();
        $this->assertNull(\App\Models\Participant::find($p->id));
    }

    // Regenerer un code supprime tous les jetons du role et ejecte l'equipe :
    // un admin non proprietaire ne doit pas pouvoir le declencher (incident du
    // 26/07 : un admin a verrouille tout le monde a la veille du tournoi).
    public function test_regenerer_un_code_est_refuse_a_un_autre_admin(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido']);
        $acces = $this->accesAdmin();
        $this->connecterEnTant('Titus');

        $this->postJson("/api/acces/{$acces->id}/regenerer")->assertStatus(403);
        $this->assertSame('ABCD1234', $acces->fresh()->code_clair, 'Le code ne doit pas avoir change');
    }

    public function test_definir_un_code_est_refuse_a_un_autre_admin(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido']);
        $acces = $this->accesAdmin();
        $this->connecterEnTant('Titus');

        $this->putJson("/api/acces/{$acces->id}", ['code' => 'HACK9999'])->assertStatus(403);
        $this->assertSame('ABCD1234', $acces->fresh()->code_clair);
    }

    public function test_le_proprietaire_peut_regenerer_un_code(): void
    {
        config(['herboquiz.proprietaire' => 'Kaido']);
        $acces = $this->accesAdmin();
        $this->connecterEnTant('Kaido');

        $this->postJson("/api/acces/{$acces->id}/regenerer")->assertOk();
        $this->assertNotSame('ABCD1234', $acces->fresh()->code_clair);
    }

    public function test_public_expose_les_poules_avec_leur_classement(): void
    {
        $a = \App\Models\Equipe::create(['nom' => 'Alpha', 'active' => true]);
        $b = \App\Models\Equipe::create(['nom' => 'Beta', 'active' => true]);
        $poule = \App\Models\Poule::create(['nom' => 'Poule A', 'nb_qualifies' => 2, 'ordre' => 1]);
        $poule->equipes()->attach([$a->id, $b->id]);
        $m = \App\Models\Manche::create([
            'libelle' => 'Poule A', 'type' => 'poule', 'poule_id' => $poule->id,
            'nb_questions_prevu' => 12, 'statut' => 'en_cours', 'question_courante' => 0, 'ordre' => 1,
        ]);
        $m->equipes()->attach([$a->id, $b->id]);
        \App\Models\Point::create([
            'manche_id' => $m->id, 'equipe_id' => $a->id, 'points' => 10,
            'attribue_par' => 'Kaido', 'role_auteur' => 'admin',
        ]);

        $data = $this->getJson('/api/public')->assertOk()->json();

        $this->assertCount(1, $data['poules']);
        $this->assertSame('Poule A', $data['poules'][0]['nom']);
        $this->assertSame('Alpha', $data['poules'][0]['classement'][0]['libelle']);
        $this->assertSame(10, $data['poules'][0]['classement'][0]['points']);
        $this->assertSame(1, $data['poules'][0]['classement'][0]['rang']);
    }
}
