<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manche extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'libelle', 'type', 'poule_id', 'phase', 'date_prevue',
        'nb_questions_prevu', 'score_cible', 'statut', 'question_courante', 'ordre',
    ];

    protected function casts(): array
    {
        return ['date_prevue' => 'datetime'];
    }

    public function poule()
    {
        return $this->belongsTo(Poule::class);
    }

    /**
     * Ordre STABLE, et c'est essentiel : en direct, l'animateur tape sur un
     * bouton parmi d'autres. Si la liste se reordonne entre deux
     * rafraichissements, il attribue le point a la mauvaise equipe.
     */
    public function equipes()
    {
        return $this->belongsToMany(Equipe::class, 'equipe_manche')
            ->orderBy('equipes.created_at')
            ->orderBy('equipes.id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('ordre');
    }

    public function points()
    {
        return $this->hasMany(Point::class);
    }

    /**
     * Classement de la manche, calcule en sommant le journal des points.
     * Aucun total n'est stocke : c'est ce qui rend l'annulation triviale et
     * l'historique incontestable.
     */
    public function classement(): array
    {
        $totaux = $this->points()->whereNull('annule_le')
            ->selectRaw('equipe_id, SUM(points) AS total')
            ->groupBy('equipe_id')
            ->pluck('total', 'equipe_id');

        return $this->equipes->map(fn ($e) => [
            'equipe_id' => $e->id,
            'libelle'   => $e->libelle,
            'points'    => (int) ($totaux[$e->id] ?? 0),
        ])->sortByDesc('points')->values()->all();
    }
}
