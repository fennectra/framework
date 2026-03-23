<?php

namespace Fennec\Attributes;

use Attribute;

/**
 * Indique qu'un endpoint retourne une image transformee.
 *
 * Usage :
 *   #[ImageTransform(maxWidth: 2000, maxHeight: 2000, allowedFormats: ['jpg', 'png', 'webp'])]
 *   public function transform() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ImageTransform
{
    public function __construct(
        public readonly int $maxWidth = 4000,
        public readonly int $maxHeight = 4000,
        public readonly array $allowedFormats = ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        public readonly int $cacheTtl = 86400,
    ) {
    }
}
