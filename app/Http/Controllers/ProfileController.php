<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's display name and/or username (pseudo).
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
        ]);

        $user->update($data);

        return new UserResource($user);
    }

    /**
     * Replace the authenticated user's avatar, deleting the previous one.
     */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $oldPath = $user->avatar_path;

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return new UserResource($user);
    }
}
