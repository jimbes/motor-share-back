<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['path'])]
class BikePhoto extends Model
{
    protected $appends = ['url'];

    public function bike(): BelongsTo
    {
        return $this->belongsTo(Bike::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(
            fn () => Storage::disk('public')->url($this->path)
        );
    }
}
