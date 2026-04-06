<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FA\Google2FA;


class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'google2fa_secret',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
    ];

     // Check if admin is super admin
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
 // Check if admin is active
    public function isActive(): bool
    {
        return $this->is_active;
    }
     // Enable 2FA
    public function enableTwoFactor()
    {
        $google2fa = new Google2FA();
        $this->google2fa_secret = $google2fa->generateSecretKey();
        $this->two_factor_enabled = true;
        $this->save();
        
        return $google2fa->getQRCodeInline(
            config('app.name'),
            $this->email,
            $this->google2fa_secret
        );
    }
    // Disable 2FA
    public function disableTwoFactor()
    {
        $this->google2fa_secret = null;
        $this->two_factor_enabled = false;
        $this->save();
    }
// Verify 2FA code
    public function verifyTwoFactorCode($code): bool
    {
        if (!$this->two_factor_enabled || !$this->google2fa_secret) {
            return true;
        }
        
        $google2fa = new Google2FA();
        return $google2fa->verifyKey($this->google2fa_secret, $code);
    }

    // Relationships
    public function loginLogs()
    {
        return $this->hasMany(AdminLoginLog::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(AdminActivityLog::class);
    }

     // Log activity
    public function logActivity($action, $modelType = null, $modelId = null, $changes = null)
    {
        return $this->activityLogs()->create([
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'changes' => $changes ? json_encode($changes) : null,
            'ip_address' => request()->ip(),
        ]);
    }

    public function failedLoginAttempts()
    {
        return $this->hasMany(AdminFailedLoginAttempt::class, 'email', 'email');
    }
}
