<?php

namespace Fennec\Core\Image;

use Fennec\Core\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

/**
 * Pipeline chainable de transformations d'images via Intervention/Image v3.
 *
 * Usage :
 *   ImageTransformer::make('photos/pic.jpg')
 *       ->resize(800, 600)
 *       ->blur(5)
 *       ->format('webp', 85)
 *       ->apply();
 */
class ImageTransformPipeline
{
    private string $path;

    /** @var array<int, array{op: string, params: array<string, mixed>}> */
    private array $operations = [];

    private string $outputFormat = '';
    private int $quality = 90;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Redimensionne l'image (conserve le ratio, downscale only par defaut).
     */
    public function resize(int $width, ?int $height = null): self
    {
        $this->operations[] = [
            'op' => 'resize',
            'params' => ['width' => $width, 'height' => $height],
        ];

        return $this;
    }

    /**
     * Redimensionne sans conserver le ratio (stretch).
     */
    public function resizeExact(int $width, int $height): self
    {
        $this->operations[] = [
            'op' => 'resizeExact',
            'params' => ['width' => $width, 'height' => $height],
        ];

        return $this;
    }

    /**
     * Decoupe l'image.
     */
    public function crop(int $width, int $height, int $x = 0, int $y = 0): self
    {
        $this->operations[] = [
            'op' => 'crop',
            'params' => ['width' => $width, 'height' => $height, 'x' => $x, 'y' => $y],
        ];

        return $this;
    }

    /**
     * Redimensionne et decoupe pour remplir exactement les dimensions (cover).
     */
    public function fit(int $width, int $height): self
    {
        $this->operations[] = [
            'op' => 'fit',
            'params' => ['width' => $width, 'height' => $height],
        ];

        return $this;
    }

    /**
     * Ajoute un watermark textuel.
     */
    public function watermark(string $text, string $position = 'bottom-right', int $fontSize = 24, string $color = 'ffffff', int $opacity = 50): self
    {
        $this->operations[] = [
            'op' => 'watermark',
            'params' => [
                'text' => $text,
                'position' => $position,
                'font_size' => $fontSize,
                'color' => $color,
                'opacity' => $opacity,
            ],
        ];

        return $this;
    }

    /**
     * Applique un flou gaussien.
     */
    public function blur(int $amount = 5): self
    {
        $this->operations[] = [
            'op' => 'blur',
            'params' => ['amount' => $amount],
        ];

        return $this;
    }

    /**
     * Augmente la nettete.
     */
    public function sharpen(int $amount = 10): self
    {
        $this->operations[] = [
            'op' => 'sharpen',
            'params' => ['amount' => $amount],
        ];

        return $this;
    }

    /**
     * Ajuste la luminosite (-100 a +100).
     */
    public function brightness(int $level): self
    {
        $this->operations[] = [
            'op' => 'brightness',
            'params' => ['level' => $level],
        ];

        return $this;
    }

    /**
     * Ajuste le contraste (-100 a +100).
     */
    public function contrast(int $level): self
    {
        $this->operations[] = [
            'op' => 'contrast',
            'params' => ['level' => $level],
        ];

        return $this;
    }

    /**
     * Rotation de l'image en degres.
     */
    public function rotate(float $angle): self
    {
        $this->operations[] = [
            'op' => 'rotate',
            'params' => ['angle' => $angle],
        ];

        return $this;
    }

    /**
     * Miroir horizontal.
     */
    public function flip(): self
    {
        $this->operations[] = ['op' => 'flip', 'params' => []];

        return $this;
    }

    /**
     * Miroir vertical.
     */
    public function flop(): self
    {
        $this->operations[] = ['op' => 'flop', 'params' => []];

        return $this;
    }

    /**
     * Convertit en niveaux de gris.
     */
    public function greyscale(): self
    {
        $this->operations[] = ['op' => 'greyscale', 'params' => []];

        return $this;
    }

    /**
     * Corrige automatiquement l'orientation EXIF.
     */
    public function orient(): self
    {
        $this->operations[] = ['op' => 'orient', 'params' => []];

        return $this;
    }

    /**
     * Definit le format de sortie.
     */
    public function format(string $format, int $quality = 90): self
    {
        $this->outputFormat = $format;
        $this->quality = $quality;

        return $this;
    }

    /**
     * Definit la qualite de sortie.
     */
    public function quality(int $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    /**
     * Applique toutes les transformations et retourne le chemin du resultat dans le Storage.
     */
    public function apply(): string
    {
        $content = ImageTransformer::loadFromStorage($this->path);
        $image = ImageTransformer::loadFromString($content);

        $image = $this->executeAll($image);

        $format = $this->outputFormat ?: ImageTransformer::detectFormat($content);
        $encoded = ImageTransformer::encode($image, $format, $this->quality);

        $outputPath = $this->buildOutputPath($format);
        Storage::put($outputPath, $encoded);

        return $outputPath;
    }

    /**
     * Applique les transformations et retourne le contenu binaire directement.
     */
    public function toBuffer(): string
    {
        $content = ImageTransformer::loadFromStorage($this->path);
        $image = ImageTransformer::loadFromString($content);

        $image = $this->executeAll($image);

        $format = $this->outputFormat ?: ImageTransformer::detectFormat($content);

        return ImageTransformer::encode($image, $format, $this->quality);
    }

    /**
     * Retourne le format de sortie effectif.
     */
    public function getOutputFormat(): string
    {
        if ($this->outputFormat !== '') {
            return $this->outputFormat;
        }

        try {
            $content = ImageTransformer::loadFromStorage($this->path);

            return ImageTransformer::detectFormat($content);
        } catch (\Throwable) {
            return 'png';
        }
    }

    /**
     * Genere une cle de cache unique pour ce pipeline.
     */
    public function cacheKey(): string
    {
        $ops = json_encode($this->operations);

        return 'img:' . md5($this->path . $ops . $this->outputFormat . $this->quality);
    }

    /**
     * Execute toutes les operations du pipeline.
     */
    private function executeAll(ImageInterface $image): ImageInterface
    {
        foreach ($this->operations as $operation) {
            $image = $this->executeOperation($image, $operation['op'], $operation['params']);
        }

        return $image;
    }

    /**
     * Execute une operation via Intervention/Image.
     */
    private function executeOperation(ImageInterface $image, string $op, array $params): ImageInterface
    {
        return match ($op) {
            'resize' => $image->scale($params['width'], $params['height']),
            'resizeExact' => $image->resize($params['width'], $params['height']),
            'crop' => $image->crop($params['width'], $params['height'], $params['x'], $params['y']),
            'fit' => $image->cover($params['width'], $params['height']),
            'blur' => $image->blur($params['amount']),
            'sharpen' => $image->sharpen($params['amount']),
            'brightness' => $image->brightness($params['level']),
            'contrast' => $image->contrast($params['level']),
            'rotate' => $image->rotate($params['angle']),
            'flip' => $image->flip(),
            'flop' => $image->flop(),
            'greyscale' => $image->greyscale(),
            'orient' => $image->orient(),
            'watermark' => $this->applyWatermark($image, $params),
            default => $image,
        };
    }

    private function applyWatermark(ImageInterface $image, array $params): ImageInterface
    {
        $text = $params['text'];
        $fontSize = $params['font_size'];
        $color = $params['color'];
        $opacity = $params['opacity'];
        $position = $params['position'];

        $image->text($text, ...match ($position) {
            'top-left' => [10, 10],
            'top-right' => [$image->width() - 10, 10],
            'bottom-left' => [10, $image->height() - 10],
            'center' => [(int) ($image->width() / 2), (int) ($image->height() / 2)],
            default => [$image->width() - 10, $image->height() - 10],
        }, font: function (FontFactory $font) use ($fontSize, $color, $opacity, $position) {
            $font->size($fontSize);
            $font->color($color . dechex((int) ($opacity * 255 / 100)));

            $align = match ($position) {
                'top-right', 'bottom-right' => 'right',
                'center' => 'center',
                default => 'left',
            };
            $font->align($align);

            $valign = match ($position) {
                'bottom-left', 'bottom-right' => 'bottom',
                'center' => 'middle',
                default => 'top',
            };
            $font->valign($valign);
        });

        return $image;
    }

    private function buildOutputPath(string $format): string
    {
        $info = pathinfo($this->path);
        $opsHash = substr(md5(json_encode($this->operations) . $this->quality), 0, 8);

        return ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '')
            . 'transforms/'
            . $info['filename'] . '_' . $opsHash . '.' . $format;
    }
}
