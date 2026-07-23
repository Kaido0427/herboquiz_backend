<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poule extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['nom', 'nb_qualifies', 'ordre'];

    public function equipes()
    {
        return $this->belongsToMany(Equipe::class, 'equipe_poule')
            ->orderBy('equipes.created_at')
            ->orderBy('equipes.id');
    }

    public function manches()
    {
        return $this->hasMany(Manche::class);
    }
}
