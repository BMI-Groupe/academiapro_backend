<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
	use HasFactory;

	protected $fillable = [
		'student_id',
		'classroom_id',
		'school_year_id',
		'enrolled_at',
	];

    protected $casts = [
        'enrolled_at' => 'date',
    ];

	public function student(): BelongsTo
	{
		return $this->belongsTo(Student::class);
	}

	public function classroom(): BelongsTo
	{
		return $this->belongsTo(Classroom::class);
	}

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}


