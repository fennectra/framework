<?php

namespace Tests\Unit;

use Fennec\Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testGetRouteIsRegistered(): void
    {
        $router = new Router();
        $router->get('/test', [FakeController::class, 'index']);

        $routes = $router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertEquals('/test', $routes[0]['path']);
        $this->assertEquals(FakeController::class, $routes[0]['controller']);
        $this->assertEquals('index', $routes[0]['action']);
    }

    public function testPostRouteIsRegistered(): void
    {
        $router = new Router();
        $router->post('/create', [FakeController::class, 'store']);

        $routes = $router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertEquals('POST', $routes[0]['method']);
    }

    public function testMultipleRoutesAreRegistered(): void
    {
        $router = new Router();
        $router->get('/a', [FakeController::class, 'index']);
        $router->post('/b', [FakeController::class, 'store']);
        $router->put('/c', [FakeController::class, 'update']);
        $router->delete('/d', [FakeController::class, 'destroy']);

        $this->assertCount(4, $router->getRoutes());
    }

    public function testRouteWithMiddleware(): void
    {
        $router = new Router();
        $router->get('/protected', [FakeController::class, 'index'], ['FakeMiddleware']);

        $routes = $router->getRoutes();

        $this->assertEquals(['FakeMiddleware'], $routes[0]['middleware']);
    }

    public function testRouteWithoutMiddleware(): void
    {
        $router = new Router();
        $router->get('/public', [FakeController::class, 'index']);

        $routes = $router->getRoutes();

        $this->assertNull($routes[0]['middleware']);
    }

    public function testDispatchCallsController(): void
    {
        $router = new Router();
        $router->get('/fake', [FakeController::class, 'index']);

        ob_start();
        $router->dispatch('GET', '/fake');
        $output = ob_get_clean();

        $this->assertEquals('fake_index_called', $output);
    }

    public function testDispatchReturns404ForUnknownRoute(): void
    {
        $router = new Router();
        $router->get('/exists', [FakeController::class, 'index']);

        $this->expectException(\Fennec\Core\HttpException::class);
        $this->expectExceptionMessage('Route non trouvée');

        $router->dispatch('GET', '/does-not-exist');
    }

    public function testDispatchWithDynamicParameter(): void
    {
        $router = new Router();
        $router->get('/users/{id}', [FakeController::class, 'show']);

        ob_start();
        $router->dispatch('GET', '/users/abc-123');
        $output = ob_get_clean();

        $this->assertEquals('show:abc-123', $output);
    }

    public function testGroupWithPrefix(): void
    {
        $router = new Router();
        $router->group(['prefix' => '/api'], function ($router) {
            $router->get('/users', [FakeController::class, 'index']);
            $router->post('/users', [FakeController::class, 'store']);
        });

        $routes = $router->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertEquals('/api/users', $routes[0]['path']);
        $this->assertEquals('/api/users', $routes[1]['path']);
    }

    public function testGroupWithMiddleware(): void
    {
        $router = new Router();
        $router->group(['middleware' => ['AuthMiddleware']], function ($router) {
            $router->get('/a', [FakeController::class, 'index']);
            $router->get('/b', [FakeController::class, 'store']);
        });

        $routes = $router->getRoutes();

        $this->assertEquals(['AuthMiddleware'], $routes[0]['middleware']);
        $this->assertEquals(['AuthMiddleware'], $routes[1]['middleware']);
    }

    public function testGroupWithPrefixAndMiddleware(): void
    {
        $router = new Router();
        $router->group([
            'prefix' => '/admin',
            'middleware' => ['AdminMiddleware'],
        ], function ($router) {
            $router->get('/dashboard', [FakeController::class, 'index']);
            $router->get('/users', [FakeController::class, 'store']);
        });

        $routes = $router->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertEquals('/admin/dashboard', $routes[0]['path']);
        $this->assertEquals('/admin/users', $routes[1]['path']);
        $this->assertEquals(['AdminMiddleware'], $routes[0]['middleware']);
    }

    public function testNestedGroups(): void
    {
        $router = new Router();
        $router->group(['prefix' => '/api', 'middleware' => ['AuthMiddleware']], function ($router) {
            $router->get('/public', [FakeController::class, 'index']);
            $router->group(['prefix' => '/admin', 'middleware' => ['AdminMiddleware']], function ($router) {
                $router->get('/stats', [FakeController::class, 'store']);
            });
        });

        $routes = $router->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertEquals('/api/public', $routes[0]['path']);
        $this->assertEquals(['AuthMiddleware'], $routes[0]['middleware']);
        $this->assertEquals('/api/admin/stats', $routes[1]['path']);
        $this->assertEquals(['AuthMiddleware', 'AdminMiddleware'], $routes[1]['middleware']);
    }

    public function testGroupDoesNotAffectOutsideRoutes(): void
    {
        $router = new Router();
        $router->get('/before', [FakeController::class, 'index']);
        $router->group(['prefix' => '/api', 'middleware' => ['Auth']], function ($router) {
            $router->get('/inside', [FakeController::class, 'store']);
        });
        $router->get('/after', [FakeController::class, 'update']);

        $routes = $router->getRoutes();

        $this->assertEquals('/before', $routes[0]['path']);
        $this->assertNull($routes[0]['middleware']);
        $this->assertEquals('/api/inside', $routes[1]['path']);
        $this->assertEquals(['Auth'], $routes[1]['middleware']);
        $this->assertEquals('/after', $routes[2]['path']);
        $this->assertNull($routes[2]['middleware']);
    }
}

/**
 * Fake controller pour les tests du router.
 */
class FakeController
{
    public function index(): void
    {
        echo 'fake_index_called';
    }

    public function store(): void
    {
        echo 'fake_store_called';
    }

    public function update(): void
    {
        echo 'fake_update_called';
    }

    public function destroy(): void
    {
        echo 'fake_destroy_called';
    }

    public function show(string $id): void
    {
        echo "show:$id";
    }
}
