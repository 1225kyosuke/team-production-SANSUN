<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    protected $fillable = ['code', 'name', 'year', 'term', 'attendance_weight', 'attitude_weight', 'assignment_weight', 'teacher_id', 'course', 'class_name', 'deadline', 'is_finalized', 'finalized_at', 'finalized_by'];

    protected function casts(): array
    {
        return ['attendance_weight' => 'integer', 'attitude_weight' => 'integer', 'assignment_weight' => 'integer', 'is_finalized' => 'boolean', 'finalized_at' => 'datetime'];
    }

    public function grades(): HasMany { return $this->hasMany(Grade::class); }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
    public function students(): BelongsToMany { return $this->belongsToMany(Student::class, 'enrollments')->withTimestamps(); }
}
