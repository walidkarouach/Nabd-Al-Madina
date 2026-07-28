<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = ['titre','description','department_id','validated_by'];
public function signalements() { return $this->hasMany(Signalement::class); }
public function departement() { return $this->belongsTo(Departement::class, 'department_id'); }
public function validateur() { return $this->belongsTo(User::class, 'validated_by'); }
}