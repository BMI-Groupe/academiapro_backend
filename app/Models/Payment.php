<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\User;
use App\Models\SchoolYear;

use App\Traits\ScopedBySchool;

class Payment extends Model
{
    use HasFactory, ScopedBySchool;

    protected $fillable = [
        'school_id',
        'student_id',
        'user_id',
        'school_year_id',
        'amount',
        'payment_date',
        'type',
        'reference',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
