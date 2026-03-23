<?php

namespace Tests\Unit;

use Fennec\Core\Storage;
use Fennec\Core\Storage\LocalDriver;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/fennec_facade_test_' . uniqid();
        mkdir($this->testDir, 0775, true);

        $storage = new Storage(new LocalDriver($this->testDir));
        Storage::setInstance($storage);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->testDir);

        // Reset singleton
        $ref = new \ReflectionClass(Storage::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);
    }

    public function testFacadePutAndGet(): void
    {
        Storage::put('test.txt', 'hello');

        $this->assertEquals('hello', Storage::get('test.txt'));
    }

    public function testFacadeExists(): void
    {
        $this->assertFalse(Storage::exists('nope.txt'));

        Storage::put('yep.txt', 'data');

        $this->assertTrue(Storage::exists('yep.txt'));
    }

    public function testFacadeDelete(): void
    {
        Storage::put('del.txt', 'data');

        $this->assertTrue(Storage::delete('del.txt'));
        $this->assertFalse(Storage::exists('del.txt'));
    }

    public function testFacadeUrl(): void
    {
        $url = Storage::url('path/to/file.jpg');

        $this->assertStringContainsString('/storage/path/to/file.jpg', $url);
    }

    public function testFacadeSize(): void
    {
        Storage::put('sized.txt', '123456');

        $this->assertEquals(6, Storage::size('sized.txt'));
    }

    public function testFacadeCopy(): void
    {
        Storage::put('src.txt', 'data');
        Storage::copy('src.txt', 'dst.txt');

        $this->assertEquals('data', Storage::get('dst.txt'));
        $this->assertTrue(Storage::exists('src.txt'));
    }

    public function testFacadeMove(): void
    {
        Storage::put('from.txt', 'data');
        Storage::move('from.txt', 'to.txt');

        $this->assertEquals('data', Storage::get('to.txt'));
        $this->assertFalse(Storage::exists('from.txt'));
    }

    public function testFacadeFiles(): void
    {
        Storage::put('a.txt', '1');
        Storage::put('b.txt', '2');

        $files = Storage::files();

        $this->assertCount(2, $files);
    }

    public function testFacadeAbsolutePath(): void
    {
        Storage::put('abs.txt', 'data');

        $this->assertNotNull(Storage::absolutePath('abs.txt'));
        $this->assertNull(Storage::absolutePath('missing.txt'));
    }

    public function testFacadeDriver(): void
    {
        $driver = Storage::driver();

        $this->assertInstanceOf(LocalDriver::class, $driver);
    }

    public function testWithDriverCreatesLocalByDefault(): void
    {
        $storage = Storage::withDriver();

        $this->assertInstanceOf(Storage::class, $storage);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
