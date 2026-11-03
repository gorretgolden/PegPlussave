<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Minute extends Model
{
    protected $fillable = ['ref', 'title', 'date', 'kind', 'attendance', 'summary', 'decisions', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'decisions' => 'array'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('date')->orderByDesc('id');
    }
}
