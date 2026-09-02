<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeHistory extends Model
{
    protected $fillable = ['grade_id', 'user_id', 'action', 'before', 'after'];
    protected function casts(): array { return ['before' => 'array', 'after' => 'array']; }
}
