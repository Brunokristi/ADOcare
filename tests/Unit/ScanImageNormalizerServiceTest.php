<?php

namespace Tests\Unit;

use App\Services\ScanImageNormalizerService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ScanImageNormalizerServiceTest extends TestCase
{
    public function test_is_allowed_mime_accepts_supported_types(): void
    {
        $service = new ScanImageNormalizerService();

        $this->assertTrue($service->isAllowedMime('image/jpeg'));
        $this->assertTrue($service->isAllowedMime('image/png'));
        $this->assertTrue($service->isAllowedMime('image/heic'));
        $this->assertFalse($service->isAllowedMime('application/pdf'));
    }

    public function test_normalize_returns_original_for_jpeg(): void
    {
        $service = new ScanImageNormalizerService();
        $file = $this->fakeUploadedFile('photo.jpg', 'image/jpeg');

        $normalized = $service->normalizeToJpegIfNeeded($file);

        $this->assertSame($file, $normalized);
        $this->assertSame('image/jpeg', $normalized->getClientMimeType());
    }

    public function test_normalize_uses_imagick_path_when_available(): void
    {
        $file = $this->fakeUploadedFile('photo.heic', 'image/heic');

        $service = new class extends ScanImageNormalizerService {
            protected function canUseImagick(): bool
            {
                return true;
            }

            protected function canUseGd(): bool
            {
                return false;
            }

            protected function convertWithImagick(UploadedFile $file): ?UploadedFile
            {
                return new UploadedFile(
                    $file->getRealPath(),
                    'converted.jpg',
                    'image/jpeg',
                    null,
                    true
                );
            }
        };

        $normalized = $service->normalizeToJpegIfNeeded($file);

        $this->assertNotSame($file, $normalized);
        $this->assertSame('image/jpeg', $normalized->getClientMimeType());
        $this->assertSame('converted.jpg', $normalized->getClientOriginalName());
    }

    public function test_normalize_uses_gd_fallback_when_imagick_unavailable(): void
    {
        $file = $this->fakeUploadedFile('photo.png', 'image/png');

        $service = new class extends ScanImageNormalizerService {
            protected function canUseImagick(): bool
            {
                return false;
            }

            protected function canUseGd(): bool
            {
                return true;
            }

            protected function convertWithGd(UploadedFile $file, string $mime): ?UploadedFile
            {
                return new UploadedFile(
                    $file->getRealPath(),
                    'converted-gd.jpg',
                    'image/jpeg',
                    null,
                    true
                );
            }
        };

        $normalized = $service->normalizeToJpegIfNeeded($file);

        $this->assertNotSame($file, $normalized);
        $this->assertSame('image/jpeg', $normalized->getClientMimeType());
        $this->assertSame('converted-gd.jpg', $normalized->getClientOriginalName());
    }

    public function test_normalize_returns_original_when_conversion_paths_fail(): void
    {
        $file = $this->fakeUploadedFile('photo.png', 'image/png');

        $service = new class extends ScanImageNormalizerService {
            protected function canUseImagick(): bool
            {
                return true;
            }

            protected function canUseGd(): bool
            {
                return true;
            }

            protected function convertWithImagick(UploadedFile $file): ?UploadedFile
            {
                throw new \RuntimeException('imagick failed');
            }

            protected function convertWithGd(UploadedFile $file, string $mime): ?UploadedFile
            {
                return null;
            }
        };

        $normalized = $service->normalizeToJpegIfNeeded($file);

        $this->assertSame($file, $normalized);
    }

    private function fakeUploadedFile(string $originalName, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'scan-test-');
        file_put_contents($path, 'image-bytes');

        return new UploadedFile($path, $originalName, $mime, null, true);
    }
}
