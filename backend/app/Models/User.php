<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\ImageStorage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'avatar_path', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $appends = ['avatar_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    // ---------------------------------------------------------------- relations

    /** Restaurants this user owns (only meaningful for the `owner` role). */
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class, 'owner_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    // ---------------------------------------------------------------- accessors

    /**
     * Shares ImageStorage::url() with every other image column, so a seeded
     * `img/…` avatar resolves to the frontend's public folder instead of being
     * sent to the upload disk, where it does not exist.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => ImageStorage::url($this->avatar_path));
    }

    // ------------------------------------------------------------------- checks

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    /** Admins and owners can reach the dashboard; customers cannot. */
    public function isStaff(): bool
    {
        return in_array($this->role, UserRole::staff(), true);
    }

    public function owns(Restaurant $restaurant): bool
    {
        return $this->id === $restaurant->owner_id;
    }
}
