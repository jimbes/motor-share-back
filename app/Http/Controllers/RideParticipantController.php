<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserSummaryResource;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\Request;

class RideParticipantController extends Controller
{
    /**
     * Tag another rider as having ridden along on this ride - only the
     * ride's owner can do this, and only for a mutual-follow friend, since
     * tagging also grants that rider permanent access to view this ride
     * (see Ride::isVisibleTo) regardless of any later unfollow.
     */
    public function store(Request $request, string $rideId)
    {
        $ride = Ride::where('user_id', $request->user()->id)->findOrFail($rideId);

        $data = $request->validate([
            'username' => ['required', 'string', 'exists:users,username'],
        ]);

        $rider = User::where('username', $data['username'])->firstOrFail();

        if ($rider->id === $ride->user_id) {
            return response()->json(['message' => 'The ride owner is already on their own ride.'], 422);
        }

        if (! $request->user()->isFriendsWith($rider)) {
            return response()->json(['message' => 'You can only tag friends who follow you back.'], 422);
        }

        $ride->participants()->syncWithoutDetaching([$rider->id]);

        return UserSummaryResource::collection($ride->participants()->get());
    }

    /**
     * Remove a tagged rider from this ride - only the ride's owner can do this.
     */
    public function destroy(Request $request, string $rideId, string $userId)
    {
        $ride = Ride::where('user_id', $request->user()->id)->findOrFail($rideId);

        $ride->participants()->detach($userId);

        return UserSummaryResource::collection($ride->participants()->get());
    }
}
