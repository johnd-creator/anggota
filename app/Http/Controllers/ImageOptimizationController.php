<?php

namespace App\Http\Controllers;

use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageOptimizationController extends Controller
{
    protected ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Get optimized image URLs (WebP + fallback)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOptimizedImage(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'size' => 'nullable|in:thumb,small,medium,large,original',
        ]);

        $path = $request->input('path');
        $size = $request->input('size', 'medium');

        // Check if file exists
        if (! Storage::disk('public')->exists($path)) {
            return response()->json([
                'error' => 'Image not found',
                'webp' => null,
                'fallback' => $this->getPlaceholderImage(),
            ], 404);
        }

        // Generate optimized image
        $fullPath = Storage::disk('public')->path($path);
        $outputPath = 'optimized/'.str_replace('/', '_', pathinfo($path, PATHINFO_FILENAME));

        $optimized = $this->imageService->optimizeImage(
            $fullPath,
            $outputPath,
            $size
        );

        return response()->json($optimized);
    }

    /**
     * Get responsive image data (all sizes)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResponsiveImages(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');

        if (! Storage::disk('public')->exists($path)) {
            return response()->json([
                'error' => 'Image not found',
                'thumb' => null,
                'small' => null,
                'medium' => null,
                'large' => null,
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($path);
        $imagePrefix = str_replace('/', '_', pathinfo($path, PATHINFO_FILENAME));

        $responsive = $this->imageService->getResponsiveImages($fullPath, $imagePrefix);

        return response()->json($responsive);
    }

    /**
     * Get placeholder image URL
     */
    protected function getPlaceholderImage(): string
    {
        return Storage::disk('public')->url('images/placeholder-avatar.png');
    }
}
