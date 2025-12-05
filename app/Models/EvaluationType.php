<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'weight',
        'school_year_id',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
