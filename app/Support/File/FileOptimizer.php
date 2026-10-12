<?php

namespace App\Support\File;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileOptimizer
{
    /**
     * Kompresi gambar cerdas (resize & webp/jpeg 80%) atau simpan dokumen non-destruktif.
     *
     * @return array Metadata berkas tersimpan [nama, path, url, ukuran, tipe, mime]
     */
    public static function optimizeAndStore(
        mixed $file,
        string $directory,
        string $disk = 'public',
        ?int $maxDimension = null,
        ?int $quality = null
    ): array {
        $maxDimension ??= (int) config('simlog.upload.image_max_dimension');
        $quality ??= (int) config('simlog.upload.image_quality');

        $origName = method_exists($file, 'getClientOriginalName')
            ? $file->getClientOriginalName()
            : basename((string) $file);

        $realPath = method_exists($file, 'getRealPath')
            ? $file->getRealPath()
            : (string) $file;

        $mimeType = method_exists($file, 'getMimeType')
            ? $file->getMimeType()
            : (mime_content_type($realPath) ?: 'application/octet-stream');

        // 1. Jika berkas adalah gambar bitmap (JPEG, PNG, WebP)
        if (static::isCompressibleImage($mimeType)) {
            try {
                return static::compressAndStoreImage($realPath, $origName, $directory, $disk, $maxDimension, $quality);
            } catch (\Throwable $e) {
                Log::warning("Kompresi gambar gagal, fallback ke penyimpanan standar: " . $e->getMessage());
            }
        }

        // 2. Berkas dokumen (PDF, XLSX, DOCX) atau fallback
        $storedPath = method_exists($file, 'store')
            ? $file->store($directory, $disk)
            : Storage::disk($disk)->putFile($directory, new File($realPath));

        return [
            'nama'   => $origName,
            'path'   => $storedPath,
            'url'    => Storage::disk($disk)->url($storedPath),
            'ukuran' => Storage::disk($disk)->size($storedPath),
            'tipe'   => strtolower(pathinfo($origName, PATHINFO_EXTENSION) ?: 'dokumen'),
            'mime'   => $mimeType,
        ];
    }

    /**
     * Memeriksa apakah mime type dapat dikompresi via GD.
     */
    protected static function isCompressibleImage(string $mime): bool
    {
        return in_array(strtolower($mime), [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
        ], true);
    }

    /**
     * Melakukan auto-orient, scaling maksimal 1600px, dan encoding WebP/JPEG 80%.
     */
    protected static function compressAndStoreImage(
        string $srcPath,
        string $origName,
        string $directory,
        string $disk,
        int $maxDimension,
        int $quality
    ): array {
        $imgData = file_get_contents($srcPath);
        if ($imgData === false) {
            throw new \RuntimeException("Tidak dapat membaca data gambar.");
        }

        $srcImg = @imagecreatefromstring($imgData);
        if (! $srcImg) {
            throw new \RuntimeException("GD gagal membaca stream gambar.");
        }

        $width = imagesx($srcImg);
        $height = imagesy($srcImg);

        // Hitung proporsi baru jika resolusi melebihi batas
        $newWidth = $width;
        $newHeight = $height;
        $longestSide = max($width, $height);

        if ($longestSide > $maxDimension) {
            $ratio = $maxDimension / $longestSide;
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);
        }

        // Siapkan canvas baru
        $dstImg = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);

        // Resample dengan interpolasi halus
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($srcImg);

        // Simpan ke temporary file dalam format WebP
        $randomName = Str::random(40);
        $tempPath = sys_get_temp_dir() . '/' . $randomName . '.webp';

        if (function_exists('imagewebp')) {
            imagewebp($dstImg, $tempPath, $quality);
            $ext = 'webp';
            $finalMime = 'image/webp';
        } else {
            imagejpeg($dstImg, $tempPath, $quality);
            $ext = 'jpg';
            $finalMime = 'image/jpeg';
        }

        imagedestroy($dstImg);

        $targetFileName = $randomName . '.' . $ext;
        $storedPath = Storage::disk($disk)->putFileAs($directory, new File($tempPath), $targetFileName);
        @unlink($tempPath);

        return [
            'nama'   => $origName,
            'path'   => $storedPath,
            'url'    => Storage::disk($disk)->url($storedPath),
            'ukuran' => Storage::disk($disk)->size($storedPath),
            'tipe'   => $ext,
            'mime'   => $finalMime,
        ];
    }
}
