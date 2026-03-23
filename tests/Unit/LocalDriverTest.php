<?php

namespace Tests\Unit;

use Fennec\Core\Storage\LocalDriver;
use PHPUnit\Framework\TestCase;

class LocalDriverTest extends TestCase
{
    private LocalDriver $driver;
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/fennec_storage_test_' . uniqid();
        mkdir($this->testDir, 0775, true);
        $this->driver = new LocalDriver($this->testDir);
    }

    protected function tearDown(): void
    {
        // Nettoyer recursivement
        $this->deleteDir($this->testDir);
    }

    // ── put / get ──

    public function testPutAndGet(): void
    {
        $this->driver->put('hello.txt', 'world');

        $this->assertEquals('world', $this->driver->get('hello.txt'));
    }

    public function testPutCreatesSubdirectories(): void
    {
        $this->driver->put('deep/nested/file.txt', 'content');

        $this->assertEquals('content', $this->driver->get('deep/nested/file.txt'));
    }

    public function testGetReturnsNullForMissingFile(): void
    {
        $this->assertNull($this->driver->get('nonexistent.txt'));
    }

    public function testPutOverwritesExistingFile(): void
    {
        $this->driver->put('file.txt', 'v1');
        $this->driver->put('file.txt', 'v2');

        $this->assertEquals('v2', $this->driver->get('file.txt'));
    }

    // ── exists ──

    public function testExistsReturnsTrueForExistingFile(): void
    {
        $this->driver->put('exists.txt', 'data');

        $this->assertTrue($this->driver->exists('exists.txt'));
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->driver->exists('missing.txt'));
    }

    // ── delete ──

    public function testDeleteRemovesFile(): void
    {
        $this->driver->put('to_delete.txt', 'data');
        $this->assertTrue($this->driver->delete('to_delete.txt'));
        $this->assertFalse($this->driver->exists('to_delete.txt'));
    }

    public function testDeleteReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->driver->delete('nope.txt'));
    }

    // ── size ──

    public function testSizeReturnsFileSize(): void
    {
        $this->driver->put('sized.txt', 'abcdef');

        $this->assertEquals(6, $this->driver->size('sized.txt'));
    }

    public function testSizeReturnsNullForMissingFile(): void
    {
        $this->assertNull($this->driver->size('missing.txt'));
    }

    // ── copy / move ──

    public function testCopyDuplicatesFile(): void
    {
        $this->driver->put('original.txt', 'data');
        $this->driver->copy('original.txt', 'copy.txt');

        $this->assertEquals('data', $this->driver->get('copy.txt'));
        $this->assertTrue($this->driver->exists('original.txt'));
    }

    public function testCopyCreatesSubdirectories(): void
    {
        $this->driver->put('src.txt', 'data');
        $this->driver->copy('src.txt', 'sub/dir/dst.txt');

        $this->assertEquals('data', $this->driver->get('sub/dir/dst.txt'));
    }

    public function testMoveTransfersFile(): void
    {
        $this->driver->put('from.txt', 'data');
        $this->driver->move('from.txt', 'to.txt');

        $this->assertEquals('data', $this->driver->get('to.txt'));
        $this->assertFalse($this->driver->exists('from.txt'));
    }

    // ── url ──

    public function testUrlReturnsPublicPath(): void
    {
        $url = $this->driver->url('avatars/photo.jpg');

        $this->assertStringContainsString('/storage/avatars/photo.jpg', $url);
    }

    // ── files ──

    public function testFilesListsAllFiles(): void
    {
        $this->driver->put('a.txt', '1');
        $this->driver->put('b.txt', '2');
        $this->driver->put('sub/c.txt', '3');

        $files = $this->driver->files();

        $this->assertCount(3, $files);
        $this->assertContains('a.txt', $files);
        $this->assertContains('b.txt', $files);
        $this->assertContains('sub/c.txt', $files);
    }

    public function testFilesListsSubdirectory(): void
    {
        $this->driver->put('root.txt', '1');
        $this->driver->put('docs/a.txt', '2');
        $this->driver->put('docs/b.txt', '3');

        $files = $this->driver->files('docs');

        $this->assertCount(2, $files);
    }

    public function testFilesReturnsEmptyForMissingDir(): void
    {
        $this->assertEmpty($this->driver->files('nonexistent'));
    }

    public function testFilesUsesForwardSlashesOnWindows(): void
    {
        $this->driver->put('win/test.txt', 'data');

        $files = $this->driver->files();

        foreach ($files as $file) {
            $this->assertStringNotContainsString('\\', $file);
        }
    }

    // ── absolutePath ──

    public function testAbsolutePathReturnsPathForExistingFile(): void
    {
        $this->driver->put('real.txt', 'data');

        $abs = $this->driver->absolutePath('real.txt');

        $this->assertNotNull($abs);
        $this->assertFileExists($abs);
    }

    public function testAbsolutePathReturnsNullForMissingFile(): void
    {
        $this->assertNull($this->driver->absolutePath('ghost.txt'));
    }

    // ── securite ──

    public function testPutStripsLeadingSlash(): void
    {
        $this->driver->put('/leading-slash.txt', 'data');

        $this->assertTrue($this->driver->exists('leading-slash.txt'));
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
