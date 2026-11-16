<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'phone', 'email', 'password'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'phone_verified_at' => 'datetime',
            'adult_attested_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function isStaff(): bool
    {
        return $this->type === UserType::Staff;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
