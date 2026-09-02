<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiSession extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'user_agent', 'ip_address', 'last_activity_at'];
    protected function casts(): array { return ['last_activity_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
