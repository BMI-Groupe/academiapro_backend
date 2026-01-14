<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedagogicalResource extends Model
{
    protected $fillable = [
        'title',
        'description',
        'file_path',
        'file_name',
        'file_type',
        'type',
        'subject_id',
        'section_id',
        'teacher_id',
        'school_year_id',
        'school_id',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
