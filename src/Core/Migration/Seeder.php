<?php

namespace Fennec\Core\Migration;

abstract class Seeder implements SeederInterface
{
    /**
     * Instantiate and run another seeder.
     */
    protected function call(string $seederClass): void
    {
        $seeder = new $seederClass();
        $seeder->run();

        $short = basename(str_replace('\\', '/', $seederClass));
        echo "  \033[32m✓\033[0m {$short}\n";
    }

    /**
     * Get a fake data generator instance.
     */
    protected function fake(): FakeDataGenerator
    {
        return new FakeDataGenerator();
    }
}
