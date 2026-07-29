<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departement extends Model
{
    protected $fillable = [
        'nom',
    ];

    public function signalements(): HasMany
    {
        return $this->hasMany(Signalement::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }
}