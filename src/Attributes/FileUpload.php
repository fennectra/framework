<?php

namespace Fennec\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class FileUpload
{
    public function __construct(
        public string $field = 'file',
        public string $description = 'Fichier à téléverser',
        public int $maxSize = 10 * 1024 * 1024,
    ) {
    }
}
