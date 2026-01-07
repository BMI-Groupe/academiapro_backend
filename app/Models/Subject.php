<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\ScopedBySchool;

class Subject extends Model
{
	use HasFactory, ScopedBySchool;

	protected $fillable = [
		'school_id',
		'school_year_id',
		'name',
		'code',
		'coefficient',
	];

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_subjects')
            ->withPivot(['coefficient', 'school_year_id'])
            ->using(SectionSubject::class)
            ->withTimestamps();
    }

    public function sectionSubjects(): HasMany
    {
        return $this->hasMany(SectionSubject::class);
    }

    // Alias pour compatibilité (à supprimer progressivement)
    public function classrooms(): BelongsToMany
    {
        return $this->sections();
    }

    public function classroomSubjects(): HasMany
    {
        return $this->sectionSubjects();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
