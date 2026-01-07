<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\ScopedBySchool;

class Teacher extends Model
{
	use HasFactory, ScopedBySchool;

	protected $fillable = [
		'school_id',
		'user_id',
		'first_name',
		'last_name',
		'phone',
		'email',
		'specialization',
		'birth_date',
	];

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

    public function sectionSubjectTeachers(): HasMany
    {
        return $this->hasMany(SectionSubjectTeacher::class);
    }

    // Alias pour compatibilité (à supprimer progressivement)
    public function classroomSubjectTeachers(): HasMany
    {
        return $this->sectionSubjectTeachers();
    }

    // Helper to get assignments for active year
    public function assignmentsForYear($schoolYearId)
    {
        return $this->sectionSubjectTeachers()
            ->where('school_year_id', $schoolYearId)
            ->with(['sectionSubject.section.classroomTemplate', 'sectionSubject.subject'])
            ->get();
    }

	public function grades(): HasMany
	{
		return $this->hasMany(Grade::class, 'graded_by');
	}

	public function schedules(): HasMany
	{
		return $this->hasMany(Schedule::class);
	}
}


