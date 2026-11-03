<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'name', 'role', 'action', 'subject'];

    public static function record(?User $user, string $action, ?string $subject = null): void
    {
        static::create([
            'user_id' => $user?->id,
            'name' => $user?->name,
            'role' => $user?->office(),
            'action' => $action,
            'subject' => $subject,
        ]);
    }
}
