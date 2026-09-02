<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    protected $fillable = ['student_id', 'subject_id', 'attendance_rate', 'attitude', 'assignment', 'score', 'evaluation'];

    protected function casts(): array
    {
        return ['attendance_rate' => 'float', 'attitude' => 'integer', 'assignment' => 'integer', 'score' => 'integer'];
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
}
