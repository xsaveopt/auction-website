<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function active(): JsonResponse
    {
        $announcement = Announcement::query()
            ->where('is_active', true)
            ->with('author:id,username')
            ->latest()
            ->first();

        /** @var \App\Models\User|null $author */
        $author = $announcement?->author;

        return response()->json([
            'announcement' => $announcement && $author
                ? [
                    'id' => $announcement->id,
                    'message' => $announcement->message,
                    'author' => $author->username,
                    'updated_at' => $announcement->updated_at,
                ] : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var array{message: string} $validated */
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // Deactivate all existing announcements
        Announcement::query()->where('is_active', true)->update(['is_active' => false]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $announcement = Announcement::create([
            'message' => $validated['message'],
            'author_id' => $user->id,
            'is_active' => true,
        ]);

        $announcement->load('author:id,username');

        /** @var \App\Models\User $author */
        $author = $announcement->author;

        return response()->json([
            'announcement' => [
                'id' => $announcement->id,
                'message' => $announcement->message,
                'author' => $author->username,
                'updated_at' => $announcement->updated_at,
            ],
        ], 201);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->update(['is_active' => false]);

        return response()->json(['message' => 'Announcement removed.']);
    }
}
