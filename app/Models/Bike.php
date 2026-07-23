<?php

namespace App\Models;

use Database\Factories\BikeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['brand', 'model', 'year', 'nickname', 'engine_cc', 'is_default'])]
class Bike extends Model
{
    /** @use HasFactory<BikeFactory> */
    use HasFactory;

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BikePhoto::class)->oldest();
    }

    /**
     * The cover photo shown in thumbnails and on the profile banner - the
     * first photo added to the gallery, or null if there are none.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->photos->first()?->url
        );
    }
}
