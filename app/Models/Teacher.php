<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
	use HasFactory;

	protected $fillable = [
		'user_id',
		'first_name',
		'last_name',
		'phone',
		'specialization',
		'birth_date',
	];

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

    public function classroomSubjectTeachers(): HasMany
    {
        return $this->hasMany(ClassroomSubjectTeacher::class);
    }

    // Helper to get assignments for active year
    public function assignmentsForYear($schoolYearId)
    {
        return $this->classroomSubjectTeachers()
            ->where('school_year_id', $schoolYearId)
            ->with(['classroomSubject.classroom', 'classroomSubject.subject'])
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


