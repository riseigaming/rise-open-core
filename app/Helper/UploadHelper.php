<?php

namespace App\Helper;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class UploadHelper
{
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public static function upload(UploadedFile $image, $title, $path = "/", $width = 100, $height = 100, bool $allowAvif = false){
        $mimeType = strtolower(trim((string) $image->getMimeType()));

        if (!array_key_exists($mimeType, self::ALLOWED_IMAGE_MIME_TYPES) || ($mimeType === 'image/avif' && ! $allowAvif)) {
            throw new InvalidArgumentException($allowAvif
                ? 'Yalnızca JPG, PNG, WEBP veya AVIF görsel yükleyebilirsiniz.'
                : 'Yalnızca JPG, PNG veya WEBP görsel yükleyebilirsiniz.');
        }

        $extension = self::ALLOWED_IMAGE_MIME_TYPES[$mimeType];

        $imageName =  $title. "-".uniqid(). ".".$extension;

        if ($mimeType === 'image/avif') {
            return self::storeAvif($image, $imageName, $path);
        }

        $img = (string) ImageManager::usingDriver(Driver::class)
            ->decodeSplFileInfo($image)->encodeUsingFileExtension($extension);

        Storage::disk("public")->put("/".$path."/". $imageName, $img);

        return $imageName;
    }

    private static function storeAvif(UploadedFile $image, string $imageName, string $path): string
    {
        $sourcePath = $image->getRealPath();
        if (! $sourcePath || ! function_exists('imagecreatefromavif')) {
            throw new InvalidArgumentException('Bu sunucu AVIF görsel yüklemeyi desteklemiyor.');
        }

        $decodedImage = @imagecreatefromavif($sourcePath);
        if ($decodedImage === false) {
            throw new InvalidArgumentException('Geçerli bir AVIF görsel yükleyin.');
        }
        imagedestroy($decodedImage);

        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw new InvalidArgumentException('AVIF görseli okunamadı.');
        }

        Storage::disk('public')->put('/'.$path.'/'.$imageName, $contents);

        return $imageName;
    }
}
