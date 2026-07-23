<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBikeRequest;
use App\Http\Resources\BikeResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BikeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return BikeResource::collection(
            $request->user()->bikes()->latest()->get()
        );
    }

    /**
     * Store a newly created resource in storage. A rider's very first bike
     * automatically becomes their default.
     */
    public function store(StoreBikeRequest $request)
    {
        $isFirstBike = $request->user()->bikes()->doesntExist();

        $bike = $request->user()->bikes()->create($request->validated());

        if ($isFirstBike) {
            $bike->update(['is_default' => true]);
        }

        return new BikeResource($bike);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $bike = $request->user()->bikes()->findOrFail($id);

        return new BikeResource($bike);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreBikeRequest $request, string $id)
    {
        $bike = $request->user()->bikes()->findOrFail($id);
        $bike->update($request->validated());

        return new BikeResource($bike);
    }

    /**
     * Remove the specified resource from storage. If it was the default
     * bike, the next most recently added one (if any) is promoted so the
     * rider always has a default as long as they have at least one bike.
     */
    public function destroy(Request $request, string $id)
    {
        $bike = $request->user()->bikes()->findOrFail($id);
        $wasDefault = $bike->is_default;
        $bike->delete();

        if ($wasDefault) {
            $request->user()->bikes()->latest()->first()?->update(['is_default' => true]);
        }

        return response()->json(null, 204);
    }

    /**
     * Mark a bike as the rider's default, clearing the flag on their others.
     */
    public function setDefault(Request $request, string $id)
    {
        $bike = $request->user()->bikes()->findOrFail($id);

        $request->user()->bikes()->where('id', '!=', $bike->id)->update(['is_default' => false]);
        $bike->update(['is_default' => true]);

        return new BikeResource($bike);
    }

    /**
     * Replace a bike's photo, deleting the previous one if there was one.
     */
    public function updatePhoto(Request $request, string $id)
    {
        $bike = $request->user()->bikes()->findOrFail($id);

        $request->validate([
            'photo' => ['required', 'image', 'max:10240'],
        ]);

        $oldPath = $bike->photo_path;

        $path = $request->file('photo')->store('bike-photos', 'public');
        $bike->update(['photo_path' => $path]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return new BikeResource($bike);
    }
}
