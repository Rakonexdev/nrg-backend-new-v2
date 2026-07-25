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
        $roleName = $this->roles->first()?->name;
        if (!$roleName && $this->isSuperAdmin()) {
            return 'super_admin';
        }
        return $roleName;
    }

    public function isSuperAdmin(): bool
    {
        $email = strtolower($this->email ?? '');
        if (str_contains($email, 'superadmin') || $email === 'superadmin@nrg.local') {
            return true;
        }

        $roleName = strtolower(str_replace([' ', '-'], '_', $this->roles->first()?->name ?? ''));
        if (in_array($roleName, ['super_admin', 'superadmin', 'super_administrator', 'superadministrator'])) {
            return true;
        }

        try {
            if ($this->hasRole('super_admin') || $this->hasRole('Super Admin') || $this->hasRole('superadmin')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Ignore role checking exceptions
        }

        if ($this->roles && $this->roles->contains(function ($r) {
            $n = strtolower(str_replace([' ', '-'], '_', $r->name ?? ''));
            return str_contains($n, 'super');
        })) {
            return true;
        }

        return false;
    }

    public function isWithinAllowedLoginTime(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

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