<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuctionImageController extends Controller
{
    public function store(Request $request, Auction $auction): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($auction->seller_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['required', 'image', 'max:5120'],
        ]);

        $nextOrder = (int) $auction->images()->max('sort_order') + 1;
        $uploaded = [];

        /** @var array<int, \Illuminate\Http\UploadedFile> $files */
        $files = $request->file('images');

        foreach ($files as $file) {
            $path = $file->store("auctions/{$auction->id}", 'public');

            $image = $auction->images()->create([
                'path' => $path,
                'sort_order' => $nextOrder++,
            ]);

            $uploaded[] = [
                'id' => $image->id,
                'url' => "/api/images/{$image->id}",
            ];
        }

        return response()->json(['images' => $uploaded], 201);
    }

    public function show(AuctionImage $image): StreamedResponse
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($image->path)) {
            abort(404);
        }

        $mime = $disk->mimeType($image->path) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $image): void {
            $stream = $disk->readStream($image->path);
            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function destroy(Request $request, AuctionImage $image): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($image->auction->seller_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Image deleted.']);
    }
}
