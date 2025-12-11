<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\ScopedBySchool;

class Classroom extends Model
{
	use HasFactory, ScopedBySchool;

	protected $fillable = [
		'school_id',
		'name',
		'code',
		'cycle',
		'level',
		'tuition_fee',
		'school_year_id',
	];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments')
            ->withPivot(['school_year_id', 'enrolled_at'])
            ->withTimestamps();
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'classroom_subjects')
            ->withPivot(['coefficient'])
            ->using(ClassroomSubject::class)
            ->withTimestamps();
    }

    public function classroomSubjects(): HasMany
    {
        return $this->hasMany(ClassroomSubject::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
