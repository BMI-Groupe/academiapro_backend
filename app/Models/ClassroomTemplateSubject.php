<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomTemplateSubject extends Pivot
{
    use HasFactory;

    protected $table = 'classroom_template_subjects';

    protected $fillable = [
        'classroom_template_id',
        'subject_id',
        'school_year_id',
        'coefficient',
    ];

    protected $casts = [
        'coefficient' => 'integer',
    ];

    public function classroomTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassroomTemplate::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
