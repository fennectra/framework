<?php

namespace Fennec\Core\Image;

use Fennec\Core\Container;
use Fennec\Core\Storage;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Service de transformation d'images via Intervention/Image v3.
 *
 * Fournit resize, crop, fit, thumbnail, watermark, blur, sharpen,
 * brightness, rotate et conversion de format.
 * S'integre avec le Storage pour lire/ecrire les images.
 */
class ImageTransformer
{
    private static ?self $instance = null;
    private ImageManager $manager;

    public function __construct(?ImageManager $manager = null)
    {
        $this->manager = $manager ?? ImageManager::gd();
    }

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            try {
                Container::getInstance()->get(self::class);
            } catch (\Throwable) {
                // Container non disponible
            }

            if (self::$instance === null) {
                throw new \RuntimeException('ImageTransformer not initialized');
            }
        }

        return self::$instance;
    }

    /**
     * Retourne le manager Intervention/Image sous-jacent.
     */
    public function manager(): ImageManager
    {
        return $this->manager;
    }

    /**
     * Cree un pipeline de transformations pour une image.
     */
    public static function make(string $path): ImageTransformPipeline
    {
        return new ImageTransformPipeline($path);
    }

    /**
     * Redimensionne une image (conserve le ratio par defaut).
     */
    public static function resize(string $path, int $width, ?int $height = null): string
    {
        return self::make($path)->resize($width, $height)->apply();
    }

    /**
     * Decoupe une image aux dimensions donnees.
     */
    public static function crop(string $path, int $width, int $height, int $x = 0, int $y = 0): string
    {
        return self::make($path)->crop($width, $height, $x, $y)->apply();
    }

    /**
     * Redimensionne et decoupe pour remplir exactement les dimensions (cover).
     */
    public static function fit(string $path, int $width, int $height): string
    {
        return self::make($path)->fit($width, $height)->apply();
    }

    /**
     * Genere une miniature carree.
     */
    public static function thumbnail(string $path, int $size = 150): string
    {
        return self::make($path)->fit($size, $size)->apply();
    }

    /**
     * Convertit dans un autre format.
     */
    public static function convert(string $path, string $format, int $quality = 90): string
    {
        return self::make($path)->format($format, $quality)->apply();
    }

    /**
     * Charge une image depuis un contenu binaire via Intervention.
     */
    public static function loadFromString(string $content): ImageInterface
    {
        return self::getInstance()->manager->read($content);
    }

    /**
     * Encode une image dans le format demande.
     */
    public static function encode(ImageInterface $image, string $format = 'png', int $quality = 90): string
    {
        $encoder = self::resolveEncoder($format, $quality);

        return $image->encode($encoder)->toString();
    }

    /**
     * Resout l'encoder Intervention pour un format donne.
     */
    public static function resolveEncoder(string $format, int $quality = 90): EncoderInterface
    {
        return match ($format) {
            'jpg', 'jpeg' => new JpegEncoder($quality),
            'png' => new PngEncoder(),
            'gif' => new GifEncoder(),
            'webp' => new WebpEncoder($quality),
            default => new AutoEncoder(quality: $quality),
        };
    }

    /**
     * Detecte le format d'une image depuis son contenu.
     */
    public static function detectFormat(string $content): string
    {
        $header = substr($content, 0, 12);

        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }
        if (str_starts_with($header, "\x89PNG")) {
            return 'png';
        }
        if (str_starts_with($header, 'GIF')) {
            return 'gif';
        }
        if (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return 'png';
    }

    /**
     * Retourne le content-type MIME pour un format.
     */
    public static function mimeType(string $format): string
    {
        return match ($format) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    /**
     * Charge une image depuis le Storage.
     */
    public static function loadFromStorage(string $path): string
    {
        $content = Storage::get($path);

        if ($content === null) {
            throw new \RuntimeException("Image introuvable dans le storage : {$path}");
        }

        return $content;
    }
}
