<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    /**
     * Follow a rider by username (idempotent - following twice is a no-op).
     */
    public function store(Request $request, string $username)
    {
        $target = User::where('username', $username)->firstOrFail();
        $user = $request->user();

        if ($target->id === $user->id) {
            return response()->json(['message' => 'You cannot follow yourself.'], 422);
        }

        $user->following()->syncWithoutDetaching([$target->id]);

        return response()->json([
            'is_following' => true,
            'followers_count' => $target->followers()->count(),
        ]);
    }

    /**
     * Unfollow a rider by username.
     */
    public function destroy(Request $request, string $username)
    {
        $target = User::where('username', $username)->firstOrFail();

        $request->user()->following()->detach($target->id);

        return response()->json([
            'is_following' => false,
            'followers_count' => $target->followers()->count(),
        ]);
    }
}
