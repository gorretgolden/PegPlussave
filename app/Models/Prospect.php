<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prospect extends Model
{
    public const STAGES = ['Approached', 'Interested', 'Registered', 'Member', 'Declined'];

    protected $fillable = ['ref', 'name', 'department', 'stage', 'note', 'created_by'];

    public function scopeOrdered($query)
    {
        return $query->orderBy('stage')->orderBy('name');
    }
}
