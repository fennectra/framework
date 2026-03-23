<?php

namespace Tests\Unit;

use Fennec\Attributes\ImageTransform;
use Fennec\Core\Image\ImageTransformer;
use Fennec\Core\Image\ImageTransformPipeline;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;

class ImageTransformTest extends TestCase
{
    // ── Format detection ──────────────────────────

    public function testDetectFormatJpeg(): void
    {
        $header = "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 8);

        $this->assertSame('jpeg', ImageTransformer::detectFormat($header));
    }

    public function testDetectFormatPng(): void
    {
        $header = "\x89PNG\r\n\x1A\n" . str_repeat("\x00", 4);

        $this->assertSame('png', ImageTransformer::detectFormat($header));
    }

    public function testDetectFormatGif(): void
    {
        $header = 'GIF89a' . str_repeat("\x00", 6);

        $this->assertSame('gif', ImageTransformer::detectFormat($header));
    }

    public function testDetectFormatWebp(): void
    {
        $header = 'RIFF' . "\x00\x00\x00\x00" . 'WEBP';

        $this->assertSame('webp', ImageTransformer::detectFormat($header));
    }

    public function testDetectFormatDefaultsToPng(): void
    {
        $this->assertSame('png', ImageTransformer::detectFormat('unknown-data'));
    }

    // ── MIME type ──────────────────────────

    public function testMimeTypeJpeg(): void
    {
        $this->assertSame('image/jpeg', ImageTransformer::mimeType('jpeg'));
        $this->assertSame('image/jpeg', ImageTransformer::mimeType('jpg'));
    }

    public function testMimeTypePng(): void
    {
        $this->assertSame('image/png', ImageTransformer::mimeType('png'));
    }

    public function testMimeTypeGif(): void
    {
        $this->assertSame('image/gif', ImageTransformer::mimeType('gif'));
    }

    public function testMimeTypeWebp(): void
    {
        $this->assertSame('image/webp', ImageTransformer::mimeType('webp'));
    }

    public function testMimeTypeDefaultsToPng(): void
    {
        $this->assertSame('image/png', ImageTransformer::mimeType('unknown'));
    }

    // ── Encoder resolution ──────────────────────────

    public function testResolveEncoderJpeg(): void
    {
        $encoder = ImageTransformer::resolveEncoder('jpeg', 85);

        $this->assertInstanceOf(JpegEncoder::class, $encoder);
    }

    public function testResolveEncoderJpg(): void
    {
        $encoder = ImageTransformer::resolveEncoder('jpg', 90);

        $this->assertInstanceOf(JpegEncoder::class, $encoder);
    }

    public function testResolveEncoderPng(): void
    {
        $encoder = ImageTransformer::resolveEncoder('png');

        $this->assertInstanceOf(PngEncoder::class, $encoder);
    }

    public function testResolveEncoderGif(): void
    {
        $encoder = ImageTransformer::resolveEncoder('gif');

        $this->assertInstanceOf(GifEncoder::class, $encoder);
    }

    public function testResolveEncoderWebp(): void
    {
        $encoder = ImageTransformer::resolveEncoder('webp', 80);

        $this->assertInstanceOf(WebpEncoder::class, $encoder);
    }

    // ── Intervention Image load/encode ──────────────────────────

    public function testLoadFromStringWithValidImage(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        $transformer = new ImageTransformer(ImageManager::gd());
        ImageTransformer::setInstance($transformer);

        $gdImage = imagecreatetruecolor(100, 50);
        ob_start();
        imagepng($gdImage);
        $pngData = ob_get_clean();
        imagedestroy($gdImage);

        $image = ImageTransformer::loadFromString($pngData);

        $this->assertSame(100, $image->width());
        $this->assertSame(50, $image->height());
    }

    public function testEncodeAsPng(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        $transformer = new ImageTransformer(ImageManager::gd());
        ImageTransformer::setInstance($transformer);

        $image = ImageManager::gd()->create(10, 10);
        $encoded = ImageTransformer::encode($image, 'png');

        $this->assertStringStartsWith("\x89PNG", $encoded);
    }

    public function testEncodeAsJpeg(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        $transformer = new ImageTransformer(ImageManager::gd());
        ImageTransformer::setInstance($transformer);

        $image = ImageManager::gd()->create(10, 10);
        $encoded = ImageTransformer::encode($image, 'jpeg');

        $this->assertStringStartsWith("\xFF\xD8\xFF", $encoded);
    }

    public function testEncodeAsGif(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        $transformer = new ImageTransformer(ImageManager::gd());
        ImageTransformer::setInstance($transformer);

        $image = ImageManager::gd()->create(10, 10);
        $encoded = ImageTransformer::encode($image, 'gif');

        $this->assertStringStartsWith('GIF', $encoded);
    }

    // ── ImageTransformer instance ──────────────────────────

    public function testSetAndGetInstance(): void
    {
        $transformer = new ImageTransformer();
        ImageTransformer::setInstance($transformer);

        $this->assertSame($transformer, ImageTransformer::getInstance());
    }

    public function testGetInstanceThrowsWhenNotInitialized(): void
    {
        $ref = new \ReflectionClass(ImageTransformer::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ImageTransformer not initialized');
        ImageTransformer::getInstance();
    }

    public function testMakeReturnsPipeline(): void
    {
        $pipeline = ImageTransformer::make('test/image.png');

        $this->assertInstanceOf(ImageTransformPipeline::class, $pipeline);
    }

    public function testManagerReturnsImageManager(): void
    {
        $transformer = new ImageTransformer();

        $this->assertInstanceOf(ImageManager::class, $transformer->manager());
    }

    public function testCustomManagerInjection(): void
    {
        $manager = ImageManager::gd();
        $transformer = new ImageTransformer($manager);

        $this->assertSame($manager, $transformer->manager());
    }

    // ── Pipeline ──────────────────────────

    public function testPipelineCacheKeyIsDeterministic(): void
    {
        $pipeline1 = (new ImageTransformPipeline('test.png'))->resize(800, 600);
        $pipeline2 = (new ImageTransformPipeline('test.png'))->resize(800, 600);

        $this->assertSame($pipeline1->cacheKey(), $pipeline2->cacheKey());
    }

    public function testPipelineCacheKeyDiffersForDifferentOps(): void
    {
        $pipeline1 = (new ImageTransformPipeline('test.png'))->resize(800, 600);
        $pipeline2 = (new ImageTransformPipeline('test.png'))->resize(400, 300);

        $this->assertNotSame($pipeline1->cacheKey(), $pipeline2->cacheKey());
    }

    public function testPipelineCacheKeyDiffersForDifferentPaths(): void
    {
        $pipeline1 = (new ImageTransformPipeline('a.png'))->resize(100);
        $pipeline2 = (new ImageTransformPipeline('b.png'))->resize(100);

        $this->assertNotSame($pipeline1->cacheKey(), $pipeline2->cacheKey());
    }

    public function testPipelineChainingReturnsSelf(): void
    {
        $pipeline = new ImageTransformPipeline('test.png');

        $result = $pipeline
            ->resize(100)
            ->crop(50, 50)
            ->fit(80, 80)
            ->blur(5)
            ->sharpen(10)
            ->brightness(20)
            ->contrast(15)
            ->rotate(90)
            ->flip()
            ->flop()
            ->greyscale()
            ->orient()
            ->format('webp')
            ->quality(85);

        $this->assertSame($pipeline, $result);
    }

    public function testPipelineCacheKeyStartsWithPrefix(): void
    {
        $pipeline = new ImageTransformPipeline('test.png');

        $this->assertStringStartsWith('img:', $pipeline->cacheKey());
    }

    public function testPipelineResizeExactReturnsSelf(): void
    {
        $pipeline = new ImageTransformPipeline('test.png');

        $result = $pipeline->resizeExact(200, 100);

        $this->assertSame($pipeline, $result);
    }

    public function testPipelineWatermarkReturnsSelf(): void
    {
        $pipeline = new ImageTransformPipeline('test.png');

        $result = $pipeline->watermark('test', 'center', 24, 'ff0000', 80);

        $this->assertSame($pipeline, $result);
    }

    // ── ImageTransform Attribute ──────────────────────────

    public function testImageTransformAttributeDefaults(): void
    {
        $attr = new ImageTransform();

        $this->assertSame(4000, $attr->maxWidth);
        $this->assertSame(4000, $attr->maxHeight);
        $this->assertSame(['jpg', 'jpeg', 'png', 'webp', 'gif'], $attr->allowedFormats);
        $this->assertSame(86400, $attr->cacheTtl);
    }

    public function testImageTransformAttributeCustomValues(): void
    {
        $attr = new ImageTransform(
            maxWidth: 2000,
            maxHeight: 1500,
            allowedFormats: ['png', 'webp'],
            cacheTtl: 3600,
        );

        $this->assertSame(2000, $attr->maxWidth);
        $this->assertSame(1500, $attr->maxHeight);
        $this->assertSame(['png', 'webp'], $attr->allowedFormats);
        $this->assertSame(3600, $attr->cacheTtl);
    }

    public function testImageTransformIsMethodAttribute(): void
    {
        $ref = new \ReflectionClass(ImageTransform::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attrs);
    }
}
