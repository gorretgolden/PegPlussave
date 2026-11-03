<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\PermissionCatalog;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = ['name', 'username', 'password', 'member_id', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function isClosed(): bool
    {
        return $this->status !== 'active';
    }

    /**
     * The office this person holds in PEG. A System Owner has no office in the group, so the
     * account type is what the screens show instead.
     */
    public function office(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * The role key stamped on everything this person makes or checks. The books record the office
     * as it was on the day, so a later change of role cannot rewrite history.
     */
    public function roleKey(): string
    {
        return $this->office() ?? 'member';
    }

    public function roleLabel(): string
    {
        return PermissionCatalog::label($this->roleKey());
    }

    /**
     * Whether this account signs in as one of the group. The System Owner does not: there is no
     * savings box to open.
     */
    public function isMember(): bool
    {
        return $this->member_id !== null && $this->roleKey() !== 'admin';
    }
}
