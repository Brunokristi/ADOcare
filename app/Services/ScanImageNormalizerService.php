<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScanImageNormalizerService
{
    /**
     * @var string[]
     */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    public function isAllowedMime(string $mime): bool
    {
        return in_array($mime, self::ALLOWED_MIMES, true);
    }

    public function normalizeToJpegIfNeeded(UploadedFile $file): UploadedFile
    {
        $mime = $file->getClientMimeType() ?: '';

        if ($mime === 'image/jpeg') {
            return $file;
        }

        if ($this->canUseImagick()) {
            try {
                $converted = $this->convertWithImagick($file);
                if ($converted) {
                    return $converted;
                }
            } catch (Throwable $e) {
                Log::warning('normalizeToJpegIfNeeded: imagick failed, storing original', [
                    'error' => $e->getMessage(),
                    'mime' => $mime,
                ]);
            }
        }

        if (in_array($mime, ['image/png', 'image/webp'], true) && $this->canUseGd()) {
            try {
                $converted = $this->convertWithGd($file, $mime);
                if ($converted) {
                    return $converted;
                }
            } catch (Throwable $e) {
                Log::warning('normalizeToJpegIfNeeded: gd failed, storing original', [
                    'error' => $e->getMessage(),
                    'mime' => $mime,
                ]);
            }
        }

        return $file;
    }

    protected function canUseImagick(): bool
    {
        return extension_loaded('imagick');
    }

    protected function canUseGd(): bool
    {
        return extension_loaded('gd');
    }

    protected function convertWithImagick(UploadedFile $file): ?UploadedFile
    {
        $imagick = new \Imagick();
        $imagick->readImage($file->getRealPath());
        $imagick->setImageFormat('jpeg');

        $tmpPath = tempnam(sys_get_temp_dir(), 'scan_') . '.jpg';
        $imagick->writeImage($tmpPath);
        $imagick->clear();
        $imagick->destroy();

        return $this->buildConvertedFile($file, $tmpPath);
    }

    protected function convertWithGd(UploadedFile $file, string $mime): ?UploadedFile
    {
        $src = null;

        if ($mime === 'image/png') {
            $src = imagecreatefrompng($file->getRealPath());
        }

        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $src = imagecreatefromwebp($file->getRealPath());
        }

        if (!$src) {
            return null;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'scan_') . '.jpg';
        imagejpeg($src, $tmpPath, 90);
        imagedestroy($src);

        return $this->buildConvertedFile($file, $tmpPath);
    }

    private function buildConvertedFile(UploadedFile $file, string $tmpPath): UploadedFile
    {
        return new UploadedFile(
            $tmpPath,
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg',
            'image/jpeg',
            null,
            true
        );
    }
}
