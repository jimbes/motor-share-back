<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Find riders by username or name (Strava-style rider search). Only
     * riders who've set a username are discoverable, since that's also
     * what their public profile URL is keyed on.
     *
     * ?scope=friends narrows results to the caller's mutual-follow friends
     * - used by the ride-companion picker, since only friends can be
     * tagged on a ride.
     */
    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $users = User::query()
            ->whereNotNull('username')
            ->where('id', '!=', $request->user()->id)
            ->when($request->query('scope') === 'friends', fn ($query) => $query->whereIn('id', $request->user()->friendIds()))
            ->where(fn ($query) => $query
                ->where('username', 'like', '%'.$data['q'].'%')
                ->orWhere('name', 'like', '%'.$data['q'].'%'))
            ->orderBy('username')
            ->limit(20)
            ->get();

        return UserSummaryResource::collection($users);
    }

    /**
     * A rider's public profile: identity plus their all-time riding stats.
     * Individual rides are private and only shared via GET /rides?user_id=
     * once both riders follow each other back (friends) - see
     * Ride::isVisibleTo().
     */
    public function show(Request $request, string $username)
    {
        $user = User::where('username', $username)->firstOrFail();
        $isSelf = $user->id === $request->user()->id;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'member_since' => $user->created_at,
            'rides_count' => $user->rides()->count(),
            'distance_meters' => (int) $user->rides()->sum('distance_meters'),
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'is_following' => ! $isSelf && $request->user()->following()->where('users.id', $user->id)->exists(),
            'is_friends' => ! $isSelf && $request->user()->isFriendsWith($user),
        ]);
    }
}
