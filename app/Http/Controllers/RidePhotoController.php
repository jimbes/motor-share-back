<?php

namespace App\Http\Controllers;

use App\Http\Resources\RidePhotoResource;
use App\Models\Ride;
use App\Models\RidePhoto;
use Illuminate\Http\Request;

class RidePhotoController extends Controller
{
    /**
     * Attach a photo to one of the authenticated user's own rides, optionally
     * geotagged with where it was taken (e.g. the rider's GPS position at
     * capture time, for photos shot mid-ride).
     */
    public function store(Request $request, string $rideId)
    {
        $ride = Ride::where('user_id', $request->user()->id)->findOrFail($rideId);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:10240'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $path = $request->file('photo')->store('ride-photos', 'public');

        $photo = $ride->photos()->create([
            'path' => $path,
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->url,
            'lat' => $photo->lat,
            'lng' => $photo->lng,
        ], 201);
    }

    /**
     * All photos across the authenticated user's own rides, newest first -
     * powers the personal photo library (grid + map) in the app.
     */
    public function mine(Request $request)
    {
        $photos = RidePhoto::query()
            ->whereHas('ride', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('ride:id,title,started_at')
            ->latest()
            ->paginate(30);

        return RidePhotoResource::collection($photos);
    }
}
