<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use Illuminate\Http\Request;

class RideLikeController extends Controller
{
    /**
     * Like a ride (idempotent - liking twice is a no-op).
     */
    public function store(Request $request, string $rideId)
    {
        $ride = Ride::findOrFail($rideId);
        abort_unless($ride->isVisibleTo($request->user()), 403, 'This ride is private.');

        $ride->likes()->firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json([
            'likes_count' => $ride->likes()->count(),
            'liked_by_me' => true,
        ]);
    }

    /**
     * Unlike a ride.
     */
    public function destroy(Request $request, string $rideId)
    {
        $ride = Ride::findOrFail($rideId);
        abort_unless($ride->isVisibleTo($request->user()), 403, 'This ride is private.');

        $ride->likes()->where('user_id', $request->user()->id)->delete();

        return response()->json([
            'likes_count' => $ride->likes()->count(),
            'liked_by_me' => false,
        ]);
    }
}
