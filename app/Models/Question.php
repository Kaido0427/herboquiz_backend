<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['manche_id', 'texte', 'reponse', 'indice', 'theme', 'ordre', 'posee_le'];

    protected function casts(): array
    {
        return ['posee_le' => 'datetime'];
    }

    public function manche()
    {
        return $this->belongsTo(Manche::class);
    }
}
