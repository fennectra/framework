<?php

namespace Tests\Unit;

use Fennec\Core\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testJsonOutputsValidJson(): void
    {
        ob_start();
        Response::json(['status' => 'ok', 'data' => 'test']);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertNotNull($decoded);
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals('test', $decoded['data']);
    }

    public function testJsonHandlesUnicodeCharacters(): void
    {
        ob_start();
        Response::json(['message' => 'Accès autorisé']);
        $output = ob_get_clean();

        $this->assertStringContainsString('Accès autorisé', $output);
        $this->assertStringNotContainsString('\\u', $output);
    }

    public function testJsonHandlesNestedData(): void
    {
        $data = [
            'user' => [
                'id' => '123',
                'roles' => ['admin', 'user'],
            ],
        ];

        ob_start();
        Response::json($data);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertEquals('123', $decoded['user']['id']);
        $this->assertCount(2, $decoded['user']['roles']);
    }

    public function testJsonHandlesEmptyArray(): void
    {
        ob_start();
        Response::json([]);
        $output = ob_get_clean();

        $this->assertEquals('[]', $output);
    }
}
