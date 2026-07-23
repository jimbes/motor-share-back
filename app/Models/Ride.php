<?php

namespace App\Models;

use Database\Factories\RideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bike_id', 'title', 'description', 'started_at',
    'duration_seconds', 'distance_meters', 'avg_speed_kmh', 'max_speed_kmh',
    'track', 'polyline_simplified', 'speed_score', 'speeding_events',
])]
class Ride extends Model
{
    /** @use HasFactory<RideFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'distance_meters' => 'integer',
            'duration_seconds' => 'integer',
            'avg_speed_kmh' => 'decimal:2',
            'max_speed_kmh' => 'decimal:2',
            'track' => 'array',
            'polyline_simplified' => 'array',
            'speed_score' => 'integer',
            'speeding_events' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bike(): BelongsTo
    {
        return $this->belongsTo(Bike::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RidePhoto::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(RideLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RideComment::class);
    }

    /**
     * Other riders tagged as having ridden along on this ride (Strava-style
     * "with" companions) - the ride is still owned/recorded by [user].
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ride_participants')->withTimestamps();
    }

    /**
     * A ride is only shared with its owner, riders tagged as companions,
     * and the owner's mutual-follow friends - never with the wider public.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->id === $this->user_id) {
            return true;
        }

        if ($this->relationLoaded('participants')) {
            if ($this->participants->contains('id', $user->id)) {
                return true;
            }
        } elseif ($this->participants()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return $user->isFriendsWith($this->user);
    }
}
