<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'school_year_id',
        'classroom_id',
        'period', // 1, 2 for semesters OR 1, 2, 3 for trimesters, null for annual
        'average',
        'rank',
        'comments',
        'generated_at',
        'absences',
    ];

    protected $casts = [
        'average' => 'decimal:2',
        'generated_at' => 'datetime',
        'period' => 'integer',
        'absences' => 'integer',
    ];

    /**
     * Get period label based on school year configuration.
     */
    public function getPeriodLabelAttribute(): string
    {
        if ($this->period === null) {
            return 'Annuel';
        }

        return $this->schoolYear?->getPeriodLabel($this->period) ?? "Période {$this->period}";
    }

    /**
     * Check if this is a periodic report card (semester or trimester).
     */
    public function isPeriodic(): bool
    {
        return $this->period !== null;
    }

    /**
     * Check if this is an annual report card.
     */
    public function isAnnual(): bool
    {
        return $this->period === null;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
