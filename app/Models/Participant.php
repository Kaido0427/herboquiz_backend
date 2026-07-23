<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Participant extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['nom', 'prenom', 'pseudo', 'telephone', 'confirme', 'note'];

    protected $appends = ['nom_complet', 'nom_affiche'];

    protected function casts(): array
    {
        return ['confirme' => 'boolean'];
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

    public function equipes()
    {
        return $this->belongsToMany(Equipe::class, 'equipe_participant');
    }
}
