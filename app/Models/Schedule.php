<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\ScopedBySchool;

class Schedule extends Model
{
	use HasFactory, ScopedBySchool;

	protected $fillable = [
		'school_id',
		'section_id',
		'subject_id',
		'teacher_id',
		'school_year_id',
		'day_of_week',
		'start_time',
		'end_time',
		'room',
	];

	protected $casts = [
		// Time fields are stored as strings in format HH:MM:SS
	];

	public function section(): BelongsTo
	{
		return $this->belongsTo(Section::class);
	}

	// Alias pour compatibilité (à supprimer progressivement)
	public function classroom(): BelongsTo
	{
		return $this->belongsTo(Section::class, 'section_id');
	}

	public function subject(): BelongsTo
	{
		return $this->belongsTo(Subject::class);
	}

	public function teacher(): BelongsTo
	{
		return $this->belongsTo(Teacher::class);
	}

	public function schoolYear(): BelongsTo
	{
		return $this->belongsTo(SchoolYear::class);
	}
}
