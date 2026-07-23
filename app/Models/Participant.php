<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Participant extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'nom', 'prenom', 'pseudo', 'telephone', 'confirme', 'note',
        'email', 'lien_facebook', 'auto_inscrit', 'inscrit_le',
    ];

    protected $appends = ['nom_complet', 'nom_affiche'];

    protected function casts(): array
    {
        return ['confirme' => 'boolean', 'auto_inscrit' => 'boolean', 'inscrit_le' => 'datetime'];
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->prenom . ' ' . $this->nom),
        );
    }

    /**
     * Ce que l'animateur doit reconnaitre en direct, c'est le pseudo affiche
     * dans le groupe — pas l'etat civil. On retombe sur le nom s'il manque.
     */
    protected function nomAffiche(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->pseudo ?: trim($this->prenom . ' ' . $this->nom),
        );
    }

    /**
     * Cle de rapprochement : sert a retrouver une fiche deja saisie par un
     * administrateur quand l'interesse vient s'inscrire lui-meme. On ignore
     * les accents, la casse et les espaces multiples — « Elie Collin » et
     * « ELIE  COLLIN » doivent se reconnaitre.
     */
    public static function normaliser(string $valeur): string
    {
        $v = mb_strtolower(trim($valeur));
        $v = strtr($v, ['á'=>'a','à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o',
                        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);

        return preg_replace('/\s+/', ' ', $v);
    }

    public function equipes()
    {
        return $this->belongsToMany(Equipe::class, 'equipe_participant');
    }
}
