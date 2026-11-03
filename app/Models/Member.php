<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'department', 'monthly_pledge', 'joined_on',
        'extension', 'status', 'exit_on', 'exit_flagged', 'exit_note',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'exit_on' => 'date',
            'exit_flagged' => 'boolean',
            'monthly_pledge' => 'integer',
        ];
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * A member who has told the Secretary they are leaving is still a member, but they may not
     * take a new loan until the settlement is done.
     */
    public function canBorrow(): bool
    {
        return $this->isActive() && ! $this->exit_flagged;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
