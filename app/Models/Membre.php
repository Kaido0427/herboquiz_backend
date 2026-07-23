<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Membre extends Model
{
    use HasUuids;

    protected $table = 'membres';

    protected $fillable = ['nom', 'role', 'ordre'];
}
