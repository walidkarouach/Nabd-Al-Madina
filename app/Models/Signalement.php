<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signalement extends Model
{
    protected $fillable = ['user_id','texte','lat','lng','photo_path','category','priority','urgency','summary','ai_analysis_status','department_id','incident_id','status'];
public function user() { return $this->belongsTo(User::class); }
public function departement() { return $this->belongsTo(Departement::class, 'department_id'); }
public function incident() { return $this->belongsTo(Incident::class); }

public function scopeOuvert($query) { return $query->whereNotIn('status', ['resolu','rejete']); }
public function scopeMemeCategorie($query, $cat) { return $query->where('category', $cat); }
}