<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => \App\Enums\Role::class, 'is_active' => 'boolean'];
    /**
     * Penentu akses ke panel admin.
     *
     * Filament menolak semua pengguna dengan 403 di lingkungan selain local
     * bila model User tidak mengimplementasikan FilamentUser. Aksesnya diikat
     * ke penanda aktif yang memang sudah dipakai aplikasi, sehingga pengguna
     * yang dinonaktifkan langsung kehilangan akses masuk.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function branch() { return $this->belongsTo(Branch::class); }
    public function employee() { return $this->belongsTo(Employee::class); }


    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\BranchScope);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    
}
