<?php

namespace App\Models;

use Database\Factories\BikeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['brand', 'model', 'year', 'nickname', 'engine_cc', 'photo_path'])]
class Bike extends Model
{
    /** @use HasFactory<BikeFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null
        );
    }
}
