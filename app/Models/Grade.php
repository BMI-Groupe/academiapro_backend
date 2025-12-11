<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\ScopedBySchool;

class Grade extends Model
{
	use HasFactory, ScopedBySchool;

	protected $fillable = [
		'school_id',
		'student_id',
		'assignment_id',
		'score',
		'notes',
		'graded_by',
		'graded_at',
	];

	protected $casts = [
		'score' => 'decimal:2',
		'graded_at' => 'datetime',
	];

	public function student(): BelongsTo
	{
		return $this->belongsTo(Student::class);
	}

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'graded_by');
    }
}
