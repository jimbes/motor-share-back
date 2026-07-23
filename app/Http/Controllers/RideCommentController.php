<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\RideComment;
use Illuminate\Http\Request;

class RideCommentController extends Controller
{
    /**
     * List comments for a ride, oldest first.
     */
    public function index(Request $request, string $rideId)
    {
        $ride = Ride::findOrFail($rideId);
        abort_unless($ride->isVisibleTo($request->user()), 403, 'This ride is private.');

        return $ride->comments()->with('user')->oldest()->get()->map(fn (RideComment $comment) => [
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at,
            'user' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
            ],
        ]);
    }

    /**
     * Post a new comment on a ride.
     */
    public function store(Request $request, string $rideId)
    {
        $ride = Ride::findOrFail($rideId);
        abort_unless($ride->isVisibleTo($request->user()), 403, 'This ride is private.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $ride->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $comment->load('user');

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at,
            'user' => [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
            ],
        ], 201);
    }

    /**
     * Delete a comment. Only the comment's author may delete it.
     */
    public function destroy(Request $request, string $commentId)
    {
        $comment = RideComment::where('user_id', $request->user()->id)->findOrFail($commentId);
        $comment->delete();

        return response()->json(null, 204);
    }
}
