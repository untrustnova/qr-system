<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'contact',
        'code',
        'user_type',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'role',
        'is_class_officer',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    protected function role(): Attribute
    {
        return Attribute::get(function () {
            if ($this->user_type === 'teacher' && $this->teacherProfile?->homeroom_class_id) {
                return 'wali';
            }

            if ($this->user_type === 'student' && $this->studentProfile?->is_class_officer) {
                return 'pengurus';
            }

            return match ($this->user_type) {
                'admin' => 'admin',
                'teacher' => 'guru',
                'student' => 'siswa',
                default => $this->user_type,
            };
        });
    }

    protected function isClassOfficer(): Attribute
    {
        return Attribute::get(fn () => (bool) $this->studentProfile?->is_class_officer);
    }

    protected function isAdmin(): Attribute
    {
        return Attribute::get(fn (): bool => $this->user_type === 'admin');
    }

    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
