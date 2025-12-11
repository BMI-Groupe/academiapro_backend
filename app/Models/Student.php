<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\ScopedBySchool;

class Student extends Model
{
	use HasFactory, ScopedBySchool;

	protected $fillable = [
		'school_id',
		'first_name',
		'last_name',
		'matricule',
		'birth_date',
		'gender',
		'address',
		'user_id',
        'parent_user_id',
        'parent_contact',
	];

	public function enrollments(): HasMany
	{
		return $this->hasMany(Enrollment::class);
	}

    public function currentEnrollment(): ?Enrollment
    {
        $activeYear = SchoolYear::active();
        if (!$activeYear) return null;

        return $this->enrollments()
            ->where('school_year_id', $activeYear->id)
            ->first();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

	public function grades(): HasMany
	{
		return $this->hasMany(Grade::class);
	}

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

