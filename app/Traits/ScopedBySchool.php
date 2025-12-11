<?php

namespace App\Traits;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;

trait ScopedBySchool
{
    protected static function bootScopedBySchool()
    {
        // Si l'utilisateur est authentifié et n'est PAS un admin global
        if (auth()->check()) {
            static::addGlobalScope('school', function (Builder $builder) {
                // Si l'utilisateur est un super-admin (rôle 'admin'), il voit tout ? 
                // Ou alors il doit sélectionner une école contexte ?
                // Pour l'instant, disons qu'il voit tout, mais les autres sont filtrés.
                // Attention : 'admin' est le rôle super-admin de la plateforme.
                // 'directeur' est l'admin d'une école.
                
                if (auth()->user()->role !== 'admin') {
                    if (auth()->user()->school_id) {
                         $builder->where('school_id', auth()->user()->school_id);
                    } else {
                        // Si pas d'école définie pour un non-admin, on bloque tout accès
                        $builder->whereRaw('1 = 0'); 
                    }
                }
            });

            static::creating(function ($model) {
                // Pour les non-admins, on force toujours l'école de l'utilisateur connecté
                if (auth()->user()->role !== 'admin' && auth()->user()->school_id) {
                    $model->school_id = auth()->user()->school_id;
                }
            });

            static::updating(function ($model) {
                // Pour les non-admins, on empêche de changer l'école d'une ressource
                if (auth()->user()->role !== 'admin' && $model->isDirty('school_id')) {
                     // On remet la valeur originale
                     $model->school_id = $model->getOriginal('school_id');
                }
            });
        }
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
