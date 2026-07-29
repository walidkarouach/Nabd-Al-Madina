<?php

namespace App\Models;

use App\Enums\SignalementPriority;
use App\Enums\SignalementStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signalement extends Model
{
    protected $fillable = [
        'user_id',
        'texte',
        'lat',
        'lng',
        'photo_path',
        'category',
        'priority',
        'urgency',
        'summary',
        'ai_analysis_status',
        'department_id',
        'incident_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'priority' => SignalementPriority::class,
            'status' => SignalementStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'department_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function scopeOuvert(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            SignalementStatus::Resolu,
            SignalementStatus::Rejete,
        ]);
    }

    public function scopeMemeCategorie(Builder $query, string $categorie): Builder
    {
        return $query->where('category', $categorie);
    }
}