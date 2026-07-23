<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Tout ce qui doit rester modifiable sans repasser par le code : textes de la
 * page publique, prix, dates, et les seuils qui pilotent la simulation.
 */
class Reglage extends Model
{
    use HasUuids;

    protected $table = 'reglages';

    protected $fillable = ['cle', 'valeur', 'type', 'groupe', 'libelle', 'aide', 'ordre'];

    /** Valeur typee : la base stocke du texte, l'API renvoie le bon type. */
    public function getValeurTypeeAttribute(): mixed
    {
        return match ($this->type) {
            'nombre'  => is_numeric($this->valeur) ? $this->valeur + 0 : null,
            'booleen' => filter_var($this->valeur, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode((string) $this->valeur, true),
            default   => $this->valeur,
        };
    }

    /** Raccourci de lecture, avec repli si la cle n'existe pas encore. */
    public static function valeur(string $cle, mixed $defaut = null): mixed
    {
        $r = static::where('cle', $cle)->first();

        return $r ? $r->valeur_typee : $defaut;
    }
}
