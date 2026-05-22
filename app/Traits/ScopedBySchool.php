<?php

namespace App\Traits;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;

trait ScopedBySchool
{
    protected static function bootScopedBySchool()
    {
        static::addGlobalScope('school', function (Builder $builder) {
            // Si l'utilisateur est authentifié et n'est PAS un admin global
            if (auth()->check()) {
                if (auth()->user()->role !== 'admin') {
                    if (auth()->user()->school_id) {
                         $builder->where($builder->getModel()->getTable() . '.school_id', auth()->user()->school_id);
                    } else {
                        // Si pas d'école définie pour un non-admin, on bloque tout accès
                        $builder->whereRaw('1 = 0'); 
                    }
                }
            }
        });

        static::creating(function ($model) {
            if (auth()->check()) {
                // Pour les non-admins, on force toujours l'école de l'utilisateur connecté
                if (auth()->user()->role !== 'admin' && auth()->user()->school_id) {
                    $model->school_id = auth()->user()->school_id;
                }
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                // Pour les non-admins, on empêche de changer l'école d'une ressource
                if (auth()->user()->role !== 'admin' && $model->isDirty('school_id')) {
                     // On remet la valeur originale
                     $model->school_id = $model->getOriginal('school_id');
                }
            }
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
