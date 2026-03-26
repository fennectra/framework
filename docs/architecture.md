# Fennec — Application Lifecycle

## 1. Boot & Mode Detection

```mermaid
graph LR
    A["> Request"]:::chamois --> B["index.php"]:::blanc
    B --> C["new App()"]:::beige
    C --> D["Middleware + Routes"]:::beige
    D --> F{"FrankenPHP?"}:::chamois
    F -->|yes| G["~ Worker loop"]:::blanc
    F -->|no| H["| Classic single run"]:::blanc

    classDef chamois fill:#d4a574,color:#3b2314,stroke:#b8895a,stroke-width:2px
    classDef blanc fill:#fefefe,color:#3b2314,stroke:#d4a574,stroke-width:2px
    classDef beige fill:#f5e6d3,color:#3b2314,stroke:#d4a574,stroke-width:2px
```

## 2. Request Pipeline

```mermaid
graph LR
    RUN["App::run()"]:::chamois --> MW["CORS > Tenant > Profiler > Log > Security"]:::beige
    MW --> R{"Route?"}:::chamois
    R -->|"x"| E["[!] 404 > JSON"]:::error
    R -->|"ok"| C["Controller"]:::blanc
    C --> D["DTO validation"]:::blanc
    D -->|invalid| E2["[!] 422 > JSON"]:::error
    D -->|valid| M["Execute method"]:::beige
    M --> RS["[ok] Response::json()"]:::success

    classDef chamois fill:#d4a574,color:#3b2314,stroke:#b8895a,stroke-width:2px
    classDef blanc fill:#fefefe,color:#3b2314,stroke:#d4a574,stroke-width:2px
    classDef beige fill:#f5e6d3,color:#3b2314,stroke:#d4a574,stroke-width:2px
    classDef success fill:#e8dcc8,color:#3b2314,stroke:#b8895a,stroke-width:2px
    classDef error fill:#c9a07a,color:#3b2314,stroke:#a07850,stroke-width:2px
```

## 3. Worker Cleanup

```mermaid
graph LR
    RS["Response sent"]:::chamois --> CL1["DB flush"]:::beige
    CL1 --> CL2["Tenant reset"]:::beige
    CL2 --> CL3["GC collect"]:::beige
    CL3 --> L{"Continue?"}:::chamois
    L -->|yes| LOOP["~ Next request"]:::blanc
    L -->|no| EXIT["x Shutdown"]:::blanc

    classDef chamois fill:#d4a574,color:#3b2314,stroke:#b8895a,stroke-width:2px
    classDef blanc fill:#fefefe,color:#3b2314,stroke:#d4a574,stroke-width:2px
    classDef beige fill:#f5e6d3,color:#3b2314,stroke:#d4a574,stroke-width:2px
```

## 4. Docker Deployment

Fennectra provides two production-ready Dockerfiles in `docker/`. Both are designed for the **Composer package structure** (not the monorepo) and should be copied to the project root for CI/CD.

### Dockerfile.frankenphp (Recommended)

Uses [FrankenPHP](https://frankenphp.dev/) with worker mode for optimal performance.

```dockerfile
FROM dunglas/frankenphp:latest-php8.3
```

**Key features:**
- PHP extensions: `pdo_pgsql`, `pdo_mysql`, `pdo_sqlite`, `gd`
- **Docker cache layer**: `composer.json` + `composer.lock` are copied first, then `composer install` runs. Application code is copied after, so dependency installation is cached across builds.
- **Caddyfile**: copied from `vendor/fennectra/framework/docker/Caddyfile` after `composer install`
- **Environment**: `FENNEC_BASE_PATH=/app`, `PORT=8080`
- Worker mode: `index.php` detects worker mode via `FRANKENPHP_WORKER` env var

### Dockerfile.fpm (Alternative)

Uses PHP-FPM with Nginx for traditional deployments.

```dockerfile
FROM php:8.3-fpm
```

**Key features:**
- PHP extensions: `pdo`, `pdo_pgsql`, `pdo_mysql`, `pdo_sqlite`
- **Docker cache layer**: same strategy as FrankenPHP (composer files first)
- **Nginx config**: copied from `vendor/fennectra/framework/docker/nginx.conf` after `composer install`
- PHP-FPM `clear_env = no` enabled for K8s/Docker env var passthrough
- **Environment**: `FENNEC_BASE_PATH=/app`
- Runs PHP-FPM + Nginx together via `sh -c "php-fpm -D && nginx -g 'daemon off;'"`

### Usage

Copy the Dockerfile to your project root:

```bash
cp vendor/fennectra/framework/docker/Dockerfile.frankenphp Dockerfile
docker build -t my-app .
docker run -p 8080:8080 --env-file .env my-app
```

### Build Structure

Both Dockerfiles follow the same layered copy pattern:

```
1. composer.json + composer.lock    (cache layer)
2. composer install --no-dev        (cached if lock unchanged)
3. app/ public/ database/ .env      (application code)
4. Server config from vendor/       (Caddyfile or nginx.conf)
5. Runtime dirs: var/, storage/     (created + chown www-data)
```
