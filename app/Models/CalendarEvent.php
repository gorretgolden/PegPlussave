<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $table = 'events';

    public const TYPES = [
        'meeting' => 'Committee meeting',
        'general' => 'General meeting',
        'deadline' => 'Deadline',
        'finance' => 'Finance',
        'activity' => 'Activity',
    ];

    protected $fillable = ['ref', 'date', 'title', 'type', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
