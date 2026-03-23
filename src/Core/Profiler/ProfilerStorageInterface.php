<?php

namespace Fennec\Core\Profiler;

interface ProfilerStorageInterface
{
    public function store(ProfileEntry $entry): void;

    /** @return ProfileEntry[] */
    public function getAll(): array;

    public function getById(string $id): ?ProfileEntry;
}
