<?php

namespace Fennec\Controllers;

use App\Middleware\Auth;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Attributes\ArrayOf;
use Fennec\Attributes\Description;
use Fennec\Attributes\FileUpload;
use Fennec\Attributes\Table;
use Fennec\Core\DB;
use Fennec\Core\Env;
use Fennec\Core\Router;

class DocsController
{
    private array $modelRegistry = [];
    private array $dtoSchemas = [];

    public function ui(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>API Documentation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <script id="api-reference" data-url="/docs/openapi"></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
HTML;
    }

    public function openapi(): void
    {
        $this->discoverModels();

        $router = Router::getCurrent();
        $routes = $router ? $router->getRoutes() : [];

        $paths = [];
        $usedSchemas = [];
        $tagDescriptions = [];

        foreach ($routes as $route) {
            if (str_starts_with($route['path'], '/docs')) {
                continue;
            }

            $path = $route['path'] ?: '/';
            $method = strtolower($route['method']);
            $tag = $this->extractTag($path);
            $requiresAuth = $this->requiresAuth($route['middleware']);

            // Collecter les descriptions de groupes par tag
            if (!empty($route['description']) && !isset($tagDescriptions[$tag])) {
                $tagDescriptions[$tag] = $route['description'];
            }
            $roles = $this->extractRoles($route['middleware']);

            $modelName = $this->resolveModelFromAction($route['controller'], $route['action']);
            $controllerShort = $this->shortClass($route['controller']);
            $operationId = $tag . '_' . $route['action'];

            // Lire les types PHP sur la méthode du contrôleur
            $methodTypes = $this->readMethodTypes($route['controller'], $route['action']);

            // Response schema
            $responseSchema = ['type' => 'object'];
            if ($methodTypes['response']) {
                $schemaName = $this->shortClass($methodTypes['response']);
                $this->registerDtoSchema($methodTypes['response']);
                $responseSchema = ['$ref' => "#/components/schemas/$schemaName"];
            } elseif ($modelName && isset($this->modelRegistry[$modelName])) {
                $usedSchemas[$modelName] = true;
                $isList = str_starts_with($route['action'], 'list');
                if ($isList) {
                    $responseSchema = [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string'],
                            'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/$modelName"]],
                            'total' => ['type' => 'integer'],
                        ],
                    ];
                } else {
                    $responseSchema = [
                        'type' => 'object',
                        'properties' => [
                            'status' => ['type' => 'string'],
                            'data' => ['$ref' => "#/components/schemas/$modelName"],
                        ],
                    ];
                }
            }

            // Réponses : 200 + status codes déclarés via #[ApiStatus]
            $responses = [
                '200' => ['description' => 'Succès', 'content' => ['application/json' => ['schema' => $responseSchema]]],
            ];

            $errorSchema = ['type' => 'object', 'properties' => [
                'status' => ['type' => 'string', 'example' => 'error'],
                'message' => ['type' => 'string'],
            ]];

            // Status codes explicites via #[ApiStatus]
            foreach ($this->readApiStatuses($route['controller'], $route['action']) as $apiStatus) {
                $responses[(string) $apiStatus->code] = [
                    'description' => $apiStatus->description,
                    'content' => ['application/json' => ['schema' => $errorSchema]],
                ];
            }

            // Status codes automatiques inférés du middleware
            if ($requiresAuth) {
                $responses += [
                    '401' => $responses['401'] ?? ['description' => 'Token invalide ou expiré', 'content' => ['application/json' => ['schema' => $errorSchema]]],
                    '403' => $responses['403'] ?? ['description' => 'Rôle insuffisant', 'content' => ['application/json' => ['schema' => $errorSchema]]],
                ];
            }

            // Lire #[ApiDescription] sur la méthode
            $apiDesc = $this->readApiDescription($route['controller'], $route['action']);

            $operation = [
                'tags' => [$tag],
                'summary' => $apiDesc ? $apiDesc->summary : "$controllerShort::$route[action]",
                'operationId' => $operationId,
                'responses' => $responses,
            ];

            if ($apiDesc && $apiDesc->description) {
                $operation['description'] = $apiDesc->description;
            }

            if ($requiresAuth) {
                $operation['security'] = [['bearerAuth' => []]];
                if ($roles) {
                    $roleInfo = 'Rôles autorisés : ' . implode(', ', $roles);
                    $operation['description'] = isset($operation['description'])
                        ? $operation['description'] . "\n\n" . $roleInfo
                        : $roleInfo;
                }
            }

            // Path parameters
            $parameters = [];
            preg_match_all('/\{(\w+)\}/', $route['path'], $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $param) {
                    $parameters[] = [
                        'name' => $param,
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ];
                }
            }

            // Query parameters : pour les GET avec un DTO Request, exposer les propriétés comme query params
            if (in_array($method, ['get', 'delete']) && $methodTypes['request']) {
                $parameters = array_merge($parameters, $this->buildQueryParams($methodTypes['request']));
            }

            if ($parameters) {
                $operation['parameters'] = $parameters;
            }

            // Request body : depuis les types PHP des paramètres du contrôleur (POST/PUT uniquement)
            if (in_array($method, ['post', 'put'])) {
                // Detecter #[FileUpload] pour multipart/form-data
                $fileUpload = $this->readFileUpload($route['controller'], $route['action']);

                if ($fileUpload) {
                    $operation['requestBody'] = [
                        'required' => true,
                        'content' => ['multipart/form-data' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                $fileUpload->field => [
                                    'type' => 'string',
                                    'format' => 'binary',
                                    'description' => $fileUpload->description,
                                ],
                            ],
                            'required' => [$fileUpload->field],
                        ]]],
                    ];
                } else {
                    $requestSchema = ['type' => 'object'];
                    if ($methodTypes['request']) {
                        $schemaName = $this->shortClass($methodTypes['request']);
                        $this->registerDtoSchema($methodTypes['request']);
                        $requestSchema = ['$ref' => "#/components/schemas/$schemaName"];
                    } elseif ($modelName && isset($this->modelRegistry[$modelName])) {
                        $requestSchema = ['$ref' => "#/components/schemas/$modelName"];
                    }
                    $operation['requestBody'] = [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => $requestSchema]],
                    ];
                }
            }

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }
            $paths[$path][$method] = $operation;
        }

        // Schemas depuis la BDD (modèles avec #[Table])
        $schemas = [];
        foreach ($usedSchemas as $modelName => $_) {
            $meta = $this->modelRegistry[$modelName];
            $schemas[$modelName] = $this->buildSchemaFromTable($meta['table'], $meta['connection']);
        }

        // Schemas depuis les DTOs (classes PHP typées)
        foreach ($this->dtoSchemas as $name => $schema) {
            $schemas[$name] = $schema;
        }

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => Env::get('APP_NAME', 'Fennectra API'),
                'description' => Env::get('APP_DESCRIPTION', 'REST API built with Fennectra'),
                'version' => Env::get('APP_VERSION', '1.0.0'),
            ],
            'servers' => [
                ['url' => Env::get('APP_URL', '') ?: $this->detectBaseUrl(), 'description' => Env::get('APP_ENV', 'dev')],
            ],
            'tags' => array_map(
                fn ($name, $desc) => ['name' => $name, 'description' => $desc],
                array_keys($tagDescriptions),
                array_values($tagDescriptions)
            ),
            'paths' => $paths ?: new \stdClass(),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                    'oauth2' => [
                        'type' => 'oauth2',
                        'flows' => [
                            'password' => [
                                'tokenUrl' => Env::get('AUTH_TOKEN_URL', '/auth/login'),
                                'scopes' => new \stdClass(),
                            ],
                        ],
                    ],
                ],
                'schemas' => $schemas ?: new \stdClass(),
            ],
        ];

        header('Content-Type: application/json');
        echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Lit les types PHP d'une méthode : paramètres DTO (request) et return type (response).
     */
    private function readMethodTypes(string $controller, string $action): array
    {
        $result = ['request' => null, 'response' => null];

        try {
            $ref = new \ReflectionMethod($controller, $action);

            // Request : premier paramètre avec un type classe (DTO)
            foreach ($ref->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $result['request'] = $type->getName();
                    break;
                }
            }

            // Response : return type classe (DTO)
            $returnType = $ref->getReturnType();
            if ($returnType instanceof \ReflectionNamedType && !$returnType->isBuiltin() && $returnType->getName() !== 'void') {
                $result['response'] = $returnType->getName();
            }
        } catch (\ReflectionException) {
        }

        return $result;
    }

    /**
     * Génère un schema OpenAPI depuis les propriétés typées d'une classe DTO.
     */
    private function registerDtoSchema(string $className): void
    {
        $shortName = $this->shortClass($className);
        if (isset($this->dtoSchemas[$shortName])) {
            return;
        }

        $ref = new \ReflectionClass($className);
        $properties = [];
        $required = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $type = $prop->getType();
            $nullable = false;
            $phpType = 'string';
            $isBuiltin = true;

            if ($type instanceof \ReflectionNamedType) {
                $phpType = $type->getName();
                $nullable = $type->allowsNull();
                $isBuiltin = $type->isBuiltin();
            }

            // Cas 1 : propriété typée comme un DTO (objet imbriqué)
            if (!$isBuiltin && $phpType !== 'mixed' && class_exists($phpType)) {
                $nestedShort = $this->shortClass($phpType);
                $this->registerDtoSchema($phpType);
                $openApiProp = ['$ref' => "#/components/schemas/$nestedShort"];
                if ($nullable) {
                    $openApiProp = ['nullable' => true, 'allOf' => [['$ref' => "#/components/schemas/$nestedShort"]]];
                }
            }
            // Cas 2 : array avec #[ArrayOf(ClassName::class)] → tableau typé
            elseif ($phpType === 'array' && ($arrayOfAttrs = $prop->getAttributes(ArrayOf::class)) !== []) {
                $itemClass = $arrayOfAttrs[0]->newInstance()->className;
                $itemShort = $this->shortClass($itemClass);
                $this->registerDtoSchema($itemClass);
                $openApiProp = ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/$itemShort"]];
                if ($nullable) {
                    $openApiProp['nullable'] = true;
                }
            }
            // Cas 3 : type scalaire classique
            else {
                $openApiProp = $this->phpTypeToOpenApi($phpType);
                if ($nullable) {
                    $openApiProp['nullable'] = true;
                }
            }

            if (!$nullable) {
                $required[] = $prop->getName();
            }

            // Lire #[Description] sur la propriété
            $descAttrs = $prop->getAttributes(Description::class);
            if ($descAttrs) {
                $openApiProp['description'] = $descAttrs[0]->newInstance()->value;
            }

            $properties[$prop->getName()] = $openApiProp;
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required) {
            $schema['required'] = $required;
        }
        $this->dtoSchemas[$shortName] = $schema;
    }

    /**
     * Génère les query parameters OpenAPI depuis les propriétés d'un DTO Request.
     */
    private function buildQueryParams(string $className): array
    {
        $params = [];

        try {
            $ref = new \ReflectionClass($className);
        } catch (\ReflectionException) {
            return $params;
        }

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $type = $prop->getType();
            $phpType = 'string';
            $nullable = false;

            if ($type instanceof \ReflectionNamedType) {
                $phpType = $type->getName();
                $nullable = $type->allowsNull();
            }

            $schema = $this->phpTypeToOpenApi($phpType);

            $param = [
                'name' => $prop->getName(),
                'in' => 'query',
                'required' => !$nullable && !$prop->hasDefaultValue(),
                'schema' => $schema,
            ];

            // Valeur par défaut
            if ($prop->hasDefaultValue() && $prop->getDefaultValue() !== null) {
                $param['schema']['default'] = $prop->getDefaultValue();
            }

            // Description
            $descAttrs = $prop->getAttributes(Description::class);
            if ($descAttrs) {
                $param['description'] = $descAttrs[0]->newInstance()->value;
            }

            $params[] = $param;
        }

        return $params;
    }

    private function phpTypeToOpenApi(string $phpType): array
    {
        return match ($phpType) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            default => ['type' => 'string'],
        };
    }

    private function discoverModels(): void
    {
        $modelsDir = FENNEC_BASE_PATH . '/app/Models';
        foreach (glob("$modelsDir/*.php") as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');
            if (!class_exists($className)) {
                continue;
            }

            $ref = new \ReflectionClass($className);
            $attrs = $ref->getAttributes(Table::class);
            if (empty($attrs)) {
                continue;
            }

            $table = $attrs[0]->newInstance();
            $this->modelRegistry[$ref->getShortName()] = [
                'class' => $className,
                'table' => $table->name,
                'connection' => $table->connection,
            ];
        }
    }

    private function resolveModelFromAction(string $controller, string $action): ?string
    {
        if (str_starts_with($action, 'list')) {
            $singular = $this->singularize(substr($action, 4));
            if (isset($this->modelRegistry[$singular])) {
                return $singular;
            }
        }

        foreach (['get', 'find', 'show'] as $prefix) {
            if (str_starts_with($action, $prefix)) {
                $name = substr($action, strlen($prefix));
                if (isset($this->modelRegistry[$name])) {
                    return $name;
                }
            }
        }

        $baseName = str_replace('Controller', '', $this->shortClass($controller));
        if (isset($this->modelRegistry[$baseName])) {
            return $baseName;
        }

        return null;
    }

    private function detectBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        return "{$scheme}://{$host}";
    }

    private function buildSchemaFromTable(string $table, string $connection): array
    {
        try {
            $db = DB::connection($connection);
            $stmt = $db->query(
                'SELECT column_name, udt_name, is_nullable
                 FROM information_schema.columns
                 WHERE table_name = :table
                 ORDER BY ordinal_position',
                ['table' => $table]
            );
            $columns = $stmt->fetchAll();

            $properties = [];
            $required = [];

            foreach ($columns as $col) {
                $prop = $this->pgTypeToOpenApi($col['udt_name']);
                if ($col['is_nullable'] === 'YES') {
                    $prop['nullable'] = true;
                } else {
                    $required[] = $col['column_name'];
                }
                $properties[$col['column_name']] = $prop;
            }

            $schema = ['type' => 'object', 'properties' => $properties];
            if ($required) {
                $schema['required'] = $required;
            }

            return $schema;
        } catch (\Exception) {
            return ['type' => 'object'];
        }
    }

    private function pgTypeToOpenApi(string $udt): array
    {
        return match ($udt) {
            'int2', 'int4', 'serial' => ['type' => 'integer', 'format' => 'int32'],
            'int8', 'bigserial' => ['type' => 'integer', 'format' => 'int64'],
            'float4' => ['type' => 'number', 'format' => 'float'],
            'float8', 'numeric' => ['type' => 'number', 'format' => 'double'],
            'bool' => ['type' => 'boolean'],
            'uuid' => ['type' => 'string', 'format' => 'uuid'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'timestamp', 'timestamptz' => ['type' => 'string', 'format' => 'date-time'],
            'jsonb', 'json' => ['type' => 'object'],
            default => ['type' => 'string'],
        };
    }

    private function singularize(string $word): string
    {
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }
        if (str_ends_with($word, 'ses') || str_ends_with($word, 'xes')) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    private function extractTag(string $path): string
    {
        $segments = array_filter(explode('/', $path));

        return ucfirst(reset($segments) ?: 'General');
    }

    private function requiresAuth(?array $middleware): bool
    {
        if (!$middleware) {
            return false;
        }
        foreach ($middleware as $mw) {
            if (is_array($mw) && ($mw[0] ?? null) === Auth::class) {
                return true;
            }
            if ($mw === Auth::class) {
                return true;
            }
        }

        return false;
    }

    private function extractRoles(?array $middleware): array
    {
        if (!$middleware) {
            return [];
        }
        foreach ($middleware as $mw) {
            if (is_array($mw) && ($mw[0] ?? null) === Auth::class) {
                return $mw[1] ?? [];
            }
        }

        return [];
    }

    private function shortClass(string $fqcn): string
    {
        return basename(str_replace('\\', '/', $fqcn));
    }

    private function readApiDescription(string $controller, string $action): ?ApiDescription
    {
        try {
            $ref = new \ReflectionMethod($controller, $action);
            $attrs = $ref->getAttributes(ApiDescription::class);

            return $attrs ? $attrs[0]->newInstance() : null;
        } catch (\ReflectionException) {
            return null;
        }
    }

    private function readFileUpload(string $controller, string $action): ?FileUpload
    {
        try {
            $ref = new \ReflectionMethod($controller, $action);
            $attrs = $ref->getAttributes(FileUpload::class);

            return $attrs ? $attrs[0]->newInstance() : null;
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * @return ApiStatus[]
     */
    private function readApiStatuses(string $controller, string $action): array
    {
        try {
            $ref = new \ReflectionMethod($controller, $action);

            return array_map(
                fn ($attr) => $attr->newInstance(),
                $ref->getAttributes(ApiStatus::class)
            );
        } catch (\ReflectionException) {
            return [];
        }
    }
}
