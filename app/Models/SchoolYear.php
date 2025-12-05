<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends Model
{
	use HasFactory;

	protected $fillable = [
		'year_start',
		'year_end',
		'label',
		'is_active',
		'start_date',
		'end_date',
	];

	protected $casts = [
		'is_active' => 'boolean',
		'start_date' => 'date',
		'end_date' => 'date',
	];

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
