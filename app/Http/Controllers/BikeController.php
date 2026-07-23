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
     * Store a newly created resource in storage.
     */
    public function store(StoreBikeRequest $request)
    {
        $bike = $request->user()->bikes()->create($request->validated());

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
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $bike = $request->user()->bikes()->findOrFail($id);
        $bike->delete();

        return response()->json(null, 204);
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
