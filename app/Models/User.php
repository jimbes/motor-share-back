<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token', 'avatar_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['avatar_url'];

    public function bikes(): HasMany
    {
        return $this->hasMany(Bike::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    /**
     * Rides this rider was tagged on as a companion, not the owner of.
     */
    public function participatingRides(): BelongsToMany
    {
        return $this->belongsToMany(Ride::class, 'ride_participants')->withTimestamps();
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    /**
     * Friendship is mutual: each rider follows the other back. Rides are
     * only shared with friends, not with one-way followers - so following
     * someone doesn't unlock their rides until they follow back too.
     */
    public function isFriendsWith(User $other): bool
    {
        return $this->following()->where('users.id', $other->id)->exists()
            && $this->followers()->where('users.id', $other->id)->exists();
    }

    /**
     * @return Collection<int, int>
     */
    public function friendIds(): Collection
    {
        $followingIds = $this->following()->pluck('users.id');
        $followerIds = $this->followers()->pluck('users.id');

        return $followingIds->intersect($followerIds)->values();
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null
        );
    }

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
        ];
    }
}
