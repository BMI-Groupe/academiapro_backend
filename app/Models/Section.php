<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\ScopedBySchool;

class Section extends Model
{
	use HasFactory, ScopedBySchool;

	protected $fillable = [
		'classroom_template_id',
		'school_year_id',
		'school_id',
		'name',
		'code',
		'tuition_fee',
		'capacity',
		'is_active',
	];

	protected $casts = [
		'tuition_fee' => 'decimal:2',
		'capacity' => 'integer',
		'is_active' => 'boolean',
	];

	public function classroomTemplate(): BelongsTo
	{
		return $this->belongsTo(ClassroomTemplate::class);
	}

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
			->withPivot(['school_year_id', 'enrolled_at', 'status'])
			->withTimestamps();
	}

	public function subjects(): BelongsToMany
	{
		return $this->belongsToMany(Subject::class, 'section_subjects')
			->withPivot(['coefficient', 'school_year_id'])
			->using(SectionSubject::class)
			->withTimestamps();
	}

	public function sectionSubjects(): HasMany
	{
		return $this->hasMany(SectionSubject::class);
	}

	public function assignments(): HasMany
	{
		return $this->hasMany(Assignment::class);
	}

	public function schedules(): HasMany
	{
		return $this->hasMany(Schedule::class);
	}

	/**
	 * Get the effective tuition fee (section fee or template fee)
	 */
	public function getEffectiveTuitionFeeAttribute(): float
	{
		return $this->tuition_fee ?? $this->classroomTemplate->tuition_fee ?? 0;
	}

	/**
	 * Get the display name (section name or template name)
	 */
	public function getDisplayNameAttribute(): string
	{
		return $this->name ?? $this->classroomTemplate->name;
	}
}

