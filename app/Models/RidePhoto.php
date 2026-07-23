<?php

namespace App\Models;

use Database\Factories\RidePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['path', 'lat', 'lng'])]
class RidePhoto extends Model
{
    /** @use HasFactory<RidePhotoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    protected function url(): Attribute
    {
        return Attribute::get(
            fn () => Storage::disk('public')->url($this->path)
        );
    }

    protected $appends = ['url'];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
