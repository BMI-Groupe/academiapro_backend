<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\ScopedBySchool;

class ClassroomTemplate extends Model
{
	use HasFactory, ScopedBySchool;

	protected $table = 'classroom_templates';

	protected $fillable = [
		'school_id',
		'name',
		'code',
		'cycle',
		'level',
		'tuition_fee',
		'is_active',
	];

	protected $casts = [
		'tuition_fee' => 'decimal:2',
		'is_active' => 'boolean',
	];

	public function sections(): HasMany
	{
		return $this->hasMany(Section::class, 'classroom_template_id');
	}

	public function school(): BelongsTo
	{
		return $this->belongsTo(School::class);
	}

	public function subjects(): BelongsToMany
	{
		return $this->belongsToMany(Subject::class, 'classroom_template_subjects')
			->withPivot(['coefficient', 'school_year_id'])
			->using(ClassroomTemplateSubject::class)
			->withTimestamps();
	}

	public function templateSubjects(): HasMany
	{
		return $this->hasMany(ClassroomTemplateSubject::class);
	}
}

