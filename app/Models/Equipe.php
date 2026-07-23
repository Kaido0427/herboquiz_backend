<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * L'entite qui concourt est toujours une equipe, meme en solo (equipe d'une
 * seule personne). C'est ce qui permet de basculer solo -> duo apres les
 * inscriptions sans rien reecrire ni perdre un point deja attribue.
 */
class Equipe extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['nom', 'active'];

    protected $appends = ['libelle'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function participants()
    {
        return $this->belongsToMany(Participant::class, 'equipe_participant');
    }

    public function points()
    {
        return $this->hasMany(Point::class);
    }

    /** Nom saisi s'il existe, sinon les pseudos des equipiers. */
    protected function libelle(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->nom
                ?: $this->participants->map(fn ($p) => $p->nom_affiche)->join(' & ')
                ?: 'Equipe',
        );
    }
}
