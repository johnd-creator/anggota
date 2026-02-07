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

    /**
     * Serve optimized image from storage with query parameters
     * This handles requests like /storage/members/photos/file.jpg?size=medium&format=webp
     *
     * @param  string  $path
     * @return \Illuminate\Http\Response
     */
    public function serveOptimizedImage($path, Request $request)
    {
        // Remove /storage/ prefix if present
        $path = str_replace('storage/', '', $path);

        // Check if file exists in storage
        if (! Storage::disk('public')->exists($path)) {
            abort(404, 'Image not found');
        }

        // Get query parameters
        $size = $request->input('size', 'medium');
        $format = $request->input('format', 'webp');

        // For member photos, just serve the original file (already compressed)
        // Only optimize if specifically requested and not already optimized
        if (str_starts_with($path, 'members/photos/')) {
            $file = Storage::disk('public')->get($path);
            $mimeType = Storage::disk('public')->mimeType($path);

            return response($file, 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=31536000', // 1 year
            ]);
        }

        // For other images, use optimization service if available
        try {
            $fullPath = Storage::disk('public')->path($path);
            $imageInfo = pathinfo($path);
            $outputPath = 'optimized/'.str_replace('/', '_', $imageInfo['filename']);

            $optimized = $this->imageService->optimizeImage(
                $fullPath,
                $outputPath,
                $size
            );

            // Serve WebP if requested and available
            if ($format === 'webp' && isset($optimized['webp'])) {
                return redirect($optimized['webp']);
            }

            // Serve fallback
            if (isset($optimized['fallback'])) {
                return redirect($optimized['fallback']);
            }

            // If optimization failed, serve original
            $file = Storage::disk('public')->get($path);
            $mimeType = Storage::disk('public')->mimeType($path);

            return response($file, 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        } catch (\Exception $e) {
            // Serve original file if optimization fails
            $file = Storage::disk('public')->get($path);
            $mimeType = Storage::disk('public')->mimeType($path);

            return response($file, 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        }
    }
}
