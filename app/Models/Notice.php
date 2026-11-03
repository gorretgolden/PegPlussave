<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $fillable = ['ref', 'title', 'body', 'pinned', 'posted_on', 'author_id', 'author_role'];

    protected function casts(): array
    {
        return ['posted_on' => 'date', 'pinned' => 'boolean'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('pinned')->orderByDesc('posted_on')->orderByDesc('id');
    }
}
