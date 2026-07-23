<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Un seul code par role, communique tel quel aux interesses.
 *
 * Le code « modo » est regenerable : les animateurs changent d'un match a
 * l'autre. Regenerer revoque aussi les jetons deja distribues, sinon l'ancien
 * animateur garderait la main et la rotation ne servirait a rien.
 */
class Acces extends Model
{
    use HasUuids;

    protected $table = 'acces';

    protected $fillable = ['role', 'code_hash', 'code_clair', 'regenere_le'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['regenere_le' => 'datetime'];
    }

    public static function genererCode(): string
    {
        // Sans caracteres ambigus : ce code se dicte au telephone et se recopie
        // a la main dans un groupe.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    public function definirCode(string $code): void
    {
        $this->code_hash   = Hash::make($code);
        $this->code_clair  = $code;
        $this->regenere_le = now();
        $this->save();
    }

    public function verifierCode(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }
}
