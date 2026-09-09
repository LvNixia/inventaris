<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Models\Scopes\BranchScope;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/*
 * Daftar kolom yang boleh diisi massal ditulis eksplisit, bukan dibuka semua
 * lewat $guarded = [], karena model ini memuat `role`. Membuka semuanya membuat
 * satu pemanggilan create() atau update() dengan masukan mentah bisa menaikkan
 * peran pengguna sendiri menjadi admin pusat.
 *
 * Sebelumnya atribut ini berisi tiga kolom saja sementara $guarded = [] juga
 * dipasang; atribut yang menang, sehingga `role` dan `is_active` diam-diam
 * tidak pernah tersimpan lewat mass assignment.
 */
#[Fillable(['name', 'email', 'password', 'role', 'branch_id', 'employee_id', 'is_active', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => Role::class, 'is_active' => 'boolean'];

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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }
}
