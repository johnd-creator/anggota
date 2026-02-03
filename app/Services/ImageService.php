<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Image Optimization Service
 *
 * Provides image optimization features:
 * - Automatic resizing
 * - WebP conversion with fallback
 * - Quality optimization
 * - Lazy loading support
 */
class ImageService
{
    protected ImageManager $manager;

    /**
     * Image size presets
     */
    const SIZE_THUMB = 'thumb';       // 150x150

    const SIZE_SMALL = 'small';       // 300x300

    const SIZE_MEDIUM = 'medium';     // 600x600

    const SIZE_LARGE = 'large';       // 1200x1200

    const SIZE_ORIGINAL = 'original'; // Keep original

    /**
     * Size dimensions in pixels
     */
    protected array $sizes = [
        self::SIZE_THUMB => 150,
        self::SIZE_SMALL => 300,
        self::SIZE_MEDIUM => 600,
        self::SIZE_LARGE => 1200,
    ];

    /**
     * Quality settings for different formats
     */
    const QUALITY_WEBP = 80;

    const QUALITY_JPEG = 85;

    const QUALITY_PNG = 9;

    public function __construct()
    {
        $this->manager = ImageManager::withDriver(Driver::class);
    }

    /**
     * Optimize and store image with WebP version
     *
     * @param  string  $path  Original image path
     * @param  string  $outputPath  Output path without extension
     * @param  string  $size  Size preset (thumb, small, medium, large, original)
     * @return array Image URLs (webp, fallback)
     */
    public function optimizeImage(string $path, string $outputPath, string $size = self::SIZE_MEDIUM): array
    {
        if (! file_exists($path)) {
            return [
                'webp' => null,
                'fallback' => null,
                'error' => 'Source image not found',
            ];
        }

        try {
            // Read image
            $image = $this->manager->read($path);

            // Get original dimensions
            $originalWidth = $image->width();
            $originalHeight = $image->height();

            // Calculate new dimensions if not original
            if ($size !== self::SIZE_ORIGINAL && isset($this->sizes[$size])) {
                $maxSize = $this->sizes[$size];

                // Maintain aspect ratio
                if ($originalWidth > $originalHeight) {
                    // Landscape
                    if ($originalWidth > $maxSize) {
                        $newWidth = $maxSize;
                        $newHeight = (int) ($originalHeight * ($maxSize / $originalWidth));
                        $image->resize($newWidth, $newHeight);
                    }
                } else {
                    // Portrait or square
                    if ($originalHeight > $maxSize) {
                        $newHeight = $maxSize;
                        $newWidth = (int) ($originalWidth * ($maxSize / $originalHeight));
                        $image->resize($newWidth, $newHeight);
                    }
                }
            }

            // Get original extension
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $extension = strtolower($extension);

            // Store WebP version
            $webpPath = $outputPath.'.webp';
            $encodedWebp = $image->toWebP(self::QUALITY_WEBP);
            Storage::disk('public')->put($webpPath, $encodedWebp);
            $webpUrl = Storage::disk('public')->url($webpPath);

            // Store fallback version (original format optimized)
            $fallbackPath = $outputPath.'.'.$extension;
            if ($extension === 'jpg' || $extension === 'jpeg') {
                $encodedFallback = $image->toJpeg(self::QUALITY_JPEG);
            } elseif ($extension === 'png') {
                $encodedFallback = $image->toPng(self::QUALITY_PNG);
            } else {
                // For other formats, just store original
                $encodedFallback = file_get_contents($path);
            }

            Storage::disk('public')->put($fallbackPath, $encodedFallback);
            $fallbackUrl = Storage::disk('public')->url($fallbackPath);

            // Get file sizes
            $webpSize = Storage::disk('public')->size($webpPath);
            $fallbackSize = Storage::disk('public')->size($fallbackPath);

            return [
                'webp' => $webpUrl,
                'fallback' => $fallbackUrl,
                'webp_size' => $webpSize,
                'fallback_size' => $fallbackSize,
                'savings_percent' => $fallbackSize > 0 ? round((1 - $webpSize / $fallbackSize) * 100, 1) : 0,
            ];

        } catch (\Exception $e) {
            return [
                'webp' => null,
                'fallback' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get responsive image data for frontend
     *
     * @param  string  $imagePath  Original image path
     * @param  string  $imagePrefix  Prefix for output files
     * @return array Responsive image data
     */
    public function getResponsiveImages(string $imagePath, string $imagePrefix): array
    {
        $fullPath = Storage::disk('public')->path($imagePath);

        if (! file_exists($fullPath)) {
            return [
                'thumb' => $this->getDefaultImage(),
                'small' => $this->getDefaultImage(),
                'medium' => $this->getDefaultImage(),
                'large' => $this->getDefaultImage(),
            ];
        }

        return [
            'thumb' => $this->optimizeImage($fullPath, "optimized/{$imagePrefix}_thumb", self::SIZE_THUMB),
            'small' => $this->optimizeImage($fullPath, "optimized/{$imagePrefix}_small", self::SIZE_SMALL),
            'medium' => $this->optimizeImage($fullPath, "optimized/{$imagePrefix}_medium", self::SIZE_MEDIUM),
            'large' => $this->optimizeImage($fullPath, "optimized/{$imagePrefix}_large", self::SIZE_LARGE),
        ];
    }

    /**
     * Get image URL with WebP support for HTML picture tag
     *
     * @param  string|null  $imagePath  Image path from storage
     * @param  string  $size  Size preset
     * @return array Image data for picture tag
     */
    public function getImageForPictureTag(?string $imagePath, string $size = self::SIZE_MEDIUM): array
    {
        if (! $imagePath) {
            return [
                'webp' => null,
                'fallback' => $this->getDefaultImage(),
                'alt' => '',
            ];
        }

        // Check if already optimized
        if (str_contains($imagePath, 'optimized/')) {
            $pathWithoutExt = preg_replace('/\.(webp|jpg|jpeg|png)$/i', '', $imagePath);
            $webpUrl = Storage::disk('public')->url($pathWithoutExt.'.webp');
            $fallbackUrl = Storage::disk('public')->url($pathWithoutExt.'.jpg');

            return [
                'webp' => $webpUrl,
                'fallback' => $fallbackUrl,
                'alt' => '',
            ];
        }

        // For non-optimized images, return original
        return [
            'webp' => null,
            'fallback' => Storage::disk('public')->url($imagePath),
            'alt' => '',
        ];
    }

    /**
     * Get default/placeholder image
     */
    protected function getDefaultImage(): string
    {
        return '/images/placeholder-avatar.svg';
    }

    /**
     * Delete optimized images
     *
     * @param  string  $imagePrefix  Prefix of images to delete
     */
    public function deleteOptimizedImages(string $imagePrefix): void
    {
        $sizes = ['thumb', 'small', 'medium', 'large'];

        foreach ($sizes as $size) {
            $webpPath = "optimized/{$imagePrefix}_{$size}.webp";
            $fallbackPath = "optimized/{$imagePrefix}_{$size}.jpg";

            if (Storage::disk('public')->exists($webpPath)) {
                Storage::disk('public')->delete($webpPath);
            }

            if (Storage::disk('public')->exists($fallbackPath)) {
                Storage::disk('public')->delete($fallbackPath);
            }
        }
    }

    /**
     * Get image dimensions
     *
     * @param  string  $path  Image path
     * @return array Width and height
     */
    public function getImageDimensions(string $path): array
    {
        try {
            $image = $this->manager->read($path);

            return [
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (\Exception $e) {
            return [
                'width' => 0,
                'height' => 0,
            ];
        }
    }
}
