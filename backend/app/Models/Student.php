<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = ['student_number', 'name', 'course', 'grade_year', 'kana', 'gender', 'birth_date', 'email', 'phone', 'postal_code', 'address', 'status', 'class_name'];

    public function grades(): HasMany { return $this->hasMany(Grade::class); }
}
