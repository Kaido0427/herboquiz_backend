<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Une connexion = un nom saisi + un role. C'est ce qui rend l'attribution des
 * points tracable alors meme que le code est partage entre plusieurs personnes.
 */
class SessionAcces extends Model
{
    use HasApiTokens, HasUuids;

    protected $table = 'sessions_acces';

    protected $fillable = ['role', 'nom', 'derniere_activite'];

    protected function casts(): array
    {
        return ['derniere_activite' => 'datetime'];
    }

    public function estAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
