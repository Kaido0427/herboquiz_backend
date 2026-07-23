<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal en ajout seul. On n'efface jamais un point : on le marque annule,
 * en gardant qui l'avait attribue et qui l'a retire.
 */
class Point extends Model
{
    use HasUuids;

    protected $fillable = [
        'manche_id', 'question_id', 'equipe_id', 'points', 'est_departage',
        'attribue_par', 'role_auteur', 'annule_le', 'annule_par',
    ];

    protected function casts(): array
    {
        return [
            'annule_le'     => 'datetime',
            'est_departage' => 'boolean',
        ];
    }

    public function equipe()
    {
        return $this->belongsTo(Equipe::class);
    }

    public function manche()
    {
        return $this->belongsTo(Manche::class);
    }

    public function scopeActifs($q)
    {
        return $q->whereNull('annule_le');
    }
}
