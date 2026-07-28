<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
   protected $fillable = ['nom'];
public function signalements() { return $this->hasMany(Signalement::class); }
public function incidents() { return $this->hasMany(Incident::class); }
}