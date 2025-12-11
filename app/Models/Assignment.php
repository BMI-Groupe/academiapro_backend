<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\ScopedBySchool;

class Assignment extends Model
{
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'title',
        'description',
        'type',
        'max_score',
        'passing_score',
        'total_score',
        'start_date',
        'due_date',
        'classroom_id',
        'subject_id',
        'school_year_id',
        'period', // 1, 2 for semesters OR 1, 2, 3 for trimesters
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'max_score' => 'decimal:2',
        'passing_score' => 'decimal:2',
        'total_score' => 'decimal:2',
        'period' => 'integer',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
