<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\ScopedBySchool;

class SchoolYear extends Model
{
	use HasFactory, ScopedBySchool;

	protected $fillable = [
		'school_id',
		'year_start',
		'year_end',
		'label',
		'is_active',
		'start_date',
		'end_date',
		'period_system', // 'trimester' or 'semester'
		'total_periods', // 2 or 3
	];

	protected $casts = [
		'is_active' => 'boolean',
		'start_date' => 'date',
		'end_date' => 'date',
		'total_periods' => 'integer',
	];

	/**
	 * Check if this school year uses trimester system.
	 */
	public function usesTrimester(): bool
	{
		return $this->period_system === 'trimester';
	}

	/**
	 * Check if this school year uses semester system.
	 */
	public function usesSemester(): bool
	{
		return $this->period_system === 'semester';
	}

	/**
	 * Get period label for a given period number.
	 */
	public function getPeriodLabel(int $period): string
	{
		if ($this->usesTrimester()) {
			return match($period) {
				1 => '1er Trimestre',
				2 => '2e Trimestre',
				3 => '3e Trimestre',
				default => 'Période inconnue'
			};
		} else {
			return match($period) {
				1 => '1er Semestre',
				2 => '2e Semestre',
				default => 'Période inconnue'
			};
		}
	}

	/**
	 * Get all period labels for this school year.
	 */
	public function getAllPeriodLabels(): array
	{
		$labels = [];
		for ($i = 1; $i <= $this->total_periods; $i++) {
			$labels[$i] = $this->getPeriodLabel($i);
		}
		return $labels;
	}

	public function enrollments(): HasMany
	{
		return $this->hasMany(Enrollment::class);
	}

    // Grades are now linked to Assignments, which are linked to SchoolYear.
    // But we can keep a direct relationship if needed, or via assignments.
    // The schema has school_year_id on grades? No, I removed it in favor of assignment linkage.
    // Wait, let me check the migration plan. 
    // "Update grades table migration... Update to link to assignments instead of direct subject/classroom/teacher"
    // So Grade does NOT have school_year_id directly anymore. It's on Assignment.
    
	public function assignments(): HasMany
	{
		return $this->hasMany(Assignment::class);
	}

	public function schedules(): HasMany
	{
		return $this->hasMany(Schedule::class);
	}

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

	/**
	 * Get the currently active school year
	 */
	public static function active()
	{
		return self::where('is_active', true)->first();
	}
}
