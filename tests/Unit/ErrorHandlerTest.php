<?php

namespace Tests\Unit;

use Fennec\Core\ErrorHandler;
use Fennec\Core\HttpException;
use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
    // ── HttpException (erreurs attendues) ──

    public function testHttpException404(): void
    {
        $handler = new ErrorHandler('prod');
        $result = $handler->buildErrorResponse(new HttpException(404, 'Not found'), 'test-id');

        $this->assertEquals(404, $result['statusCode']);
        $this->assertEquals('error', $result['response']['status']);
        $this->assertEquals('Not found', $result['response']['message']);
        $this->assertEquals('test-id', $result['response']['request_id']);
    }

    public function testHttpExceptionNeverShowsTrace(): void
    {
        $handler = new ErrorHandler('dev');
        $result = $handler->buildErrorResponse(new HttpException(400, 'Bad'));

        $this->assertArrayNotHasKey('trace', $result['response']);
        $this->assertArrayNotHasKey('exception', $result['response']);
        $this->assertArrayNotHasKey('file', $result['response']);
    }

    public function testHttpExceptionIncludesValidationErrors(): void
    {
        $handler = new ErrorHandler('dev');
        $result = $handler->buildErrorResponse(
            new HttpException(422, 'Validation failed', ['name' => 'required'])
        );

        $this->assertEquals(422, $result['statusCode']);
        $this->assertEquals('Validation failed', $result['response']['message']);
        $this->assertEquals(['name' => 'required'], $result['response']['errors']);
    }

    public function testHttpExceptionOmitsEmptyErrors(): void
    {
        $handler = new ErrorHandler('prod');
        $result = $handler->buildErrorResponse(new HttpException(404, 'Not found'));

        $this->assertArrayNotHasKey('errors', $result['response']);
    }

    // ── Vraies erreurs (RuntimeException, etc.) ──

    public function testRealExceptionShowsTraceInDev(): void
    {
        $handler = new ErrorHandler('dev');
        $result = $handler->buildErrorResponse(new \RuntimeException('Something broke'));

        $this->assertEquals(500, $result['statusCode']);
        $this->assertEquals('Something broke', $result['response']['message']);
        $this->assertArrayHasKey('trace', $result['response']);
        $this->assertArrayHasKey('exception', $result['response']);
        $this->assertEquals('RuntimeException', $result['response']['exception']);
        $this->assertIsArray($result['response']['trace']);
        $this->assertLessThanOrEqual(10, count($result['response']['trace']));
    }

    public function testRealExceptionHidesDetailsInProd(): void
    {
        $handler = new ErrorHandler('prod');
        $result = $handler->buildErrorResponse(new \RuntimeException('SQL password leaked'));

        $this->assertEquals(500, $result['statusCode']);
        $this->assertEquals('Erreur interne du serveur', $result['response']['message']);
        $this->assertArrayNotHasKey('trace', $result['response']);
        $this->assertArrayNotHasKey('exception', $result['response']);
    }

    // ── Structure de la reponse ──

    public function testResponseAlwaysIncludesRequestId(): void
    {
        $handler = new ErrorHandler('prod');
        $result = $handler->buildErrorResponse(new HttpException(400, 'Bad'));

        $this->assertArrayHasKey('request_id', $result['response']);
        $this->assertNotEmpty($result['response']['request_id']);
    }

    public function testResponseAlwaysIncludesTimestamp(): void
    {
        $handler = new ErrorHandler('prod');
        $result = $handler->buildErrorResponse(new HttpException(400, 'Bad'));

        $this->assertArrayHasKey('timestamp', $result['response']);
        $this->assertNotFalse(strtotime($result['response']['timestamp']));
    }

    public function testCustomRequestIdIsUsed(): void
    {
        $handler = new ErrorHandler('prod');
        $result = $handler->buildErrorResponse(new HttpException(400, 'Bad'), 'custom-id-123');

        $this->assertEquals('custom-id-123', $result['response']['request_id']);
    }

    // ── handleError ──

    public function testHandleErrorThrowsErrorException(): void
    {
        $handler = new ErrorHandler('prod');

        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('test warning');

        $handler->handleError(E_WARNING, 'test warning', __FILE__, __LINE__);
    }

    // ── Status codes HTTP ──

    public function testDifferentHttpStatusCodes(): void
    {
        $handler = new ErrorHandler('prod');

        $cases = [
            [400, 'Bad Request'],
            [401, 'Unauthorized'],
            [403, 'Forbidden'],
            [404, 'Not Found'],
            [413, 'Payload Too Large'],
            [415, 'Unsupported Media Type'],
            [422, 'Unprocessable Entity'],
            [429, 'Too Many Requests'],
        ];

        foreach ($cases as [$code, $message]) {
            $result = $handler->buildErrorResponse(new HttpException($code, $message));

            $this->assertEquals($code, $result['statusCode']);
            $this->assertEquals($message, $result['response']['message']);
        }
    }
}
