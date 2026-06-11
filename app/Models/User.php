<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'raw_password', 'mobile', 'is_active', 'allowed_login_shifts',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'allowed_login_shifts' => 'array',
    ];

    protected $appends = ['role'];

    public function getRoleAttribute()
    {
        return $this->roles->first()?->name;
    }

    public function isWithinAllowedLoginTime(): bool
    {
        if (empty($this->allowed_login_shifts) || !is_array($this->allowed_login_shifts) || count($this->allowed_login_shifts) === 0) {
            return true;
        }

        $now = now()->format('H:i');
        
        foreach ($this->allowed_login_shifts as $shift) {
            if (empty($shift['start']) || empty($shift['end'])) continue;
            
            $start = substr($shift['start'], 0, 5);
            $end = substr($shift['end'], 0, 5);

            if ($start <= $end) {
                if ($now >= $start && $now <= $end) return true;
            } else {
                if ($now >= $start || $now <= $end) return true;
            }
        }

        return false;
    }
}