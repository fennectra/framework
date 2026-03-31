# Fennec Documentation

> Technical documentation for the high-performance PHP 8.3+ framework with FrankenPHP worker.

*Generated on 2026-03-31 — 35 documented modules*

---

## Infrastructure

| Module | Description |
|---|---|
| [App](app.md) | Main entry point: bootstrap, dependency injection and FrankenPHP worker loop |
| [Router](router.md) | HTTP router with groups, middlewares, dynamic parameters, DTO validation and Before/After events |
| [Container](container.md) | Lightweight dependency injection container with autowiring, singletons and recursive resolution |
| [HTTP](http.md) | Request/Response abstraction for HTTP request processing and JSON responses |
| [Error Handling](error-handling.md) | Centralized error handling with structured JSON responses, logging and dev/prod mode |
| [CLI](cli.md) | Extensible command system with automatic discovery via PHP 8.3+ attributes |

## Data

| Module | Description |
|---|---|
| [Database](database.md) | Multi-driver abstraction layer with connection management, QueryBuilder and multi-tenancy |
| [Model ORM](model-orm.md) | Lightweight ActiveRecord ORM with relations, eager loading, soft delete, casting and pagination |
| [Collection](collection.md) | Typed and immutable collection for manipulating ORM results with a fluent functional API |
| [Validation](validation.md) | Declarative validation via PHP 8 attributes for DTOs, integrated with the router |

## Security

| Module | Description |
|---|---|
| [Authentication](authentication.md) | Complete JWT with password policy, account lockout and security logging |
| [Middleware](middleware.md) | PSR-like middleware pipeline for intercepting and transforming HTTP requests/responses |
| [Security](security.md) | Password policy, account lockout, secure logging and IP filtering |
| [Encryption](encryption.md) | Transparent AES-256-GCM encryption of sensitive fields via `#[Encrypted]` |
| [Rate Limiter](rate-limiter.md) | Sliding window rate limiter with interchangeable stores and PHP 8 attribute |

## Events & Async

| Module | Description |
|---|---|
| [Events](events.md) | Decoupled events with sync/Redis/database brokers and automatic listener discovery |
| [Queue / Jobs](queue.md) | Multi-driver async queue (Redis, database) with retries and failure handling |
| [Webhooks](webhook.md) | Outgoing HTTP notifications with HMAC-SHA256 signature, async delivery and automatic retry |
| [Broadcasting](broadcasting.md) | Real-time broadcasting via Server-Sent Events (SSE) and Redis Streams |
| [Scheduler](scheduler.md) | Cron task scheduler with anti-overlap protection via Redis |

## Storage

| Module | Description |
|---|---|
| [Cache](cache.md) | Multi-driver cache (file, Redis) with tags, route cache and attribute cache |
| [Storage](storage.md) | Multi-driver file storage (local, S3, GCS) with static facade and built-in upload |
| [Redis](redis.md) | Shared Redis connection with lazy loading, atomic distributed lock and worker-safe management |

## Observability

| Module | Description |
|---|---|
| [Logging](logging.md) | Monolog logging with automatic sensitive data masking and worker support |
| [Profiler](profiler.md) | HTTP request profiling: duration, memory, SQL, N+1 detection, events and middlewares |
| [Audit Trail](audit-trail.md) | Automatic logging of Model changes for SOC 2 and ISO 27001 compliance |

## Business

| Module | Description |
|---|---|
| [NF525](nf525.md) | NF525 fiscal compliance: immutability, sequential numbering, SHA-256 chaining, closings and FEC export |
| [Notifications](notifications.md) | Multi-channel notifications (database, mail, Slack, webhook) with `HasNotifications` trait |
| [Feature Flags](feature-flags.md) | Persistent feature flags with Redis cache, conditional rules and CLI command |
| [State Machine](state-machine.md) | Declarative state machine via PHP 8 attribute with validated transitions and events |

## Tools

| Module | Description |
|---|---|
| [Image](image.md) | Image transformation with chainable pipeline, format detection and Storage integration |
| [OAuth / OIDC](oauth.md) | OAuth2 and OpenID Connect with Google, GitHub, and any OIDC provider (France Travail, Keycloak, Azure AD...) |
| [SAML 2.0](saml.md) | Native SAML 2.0 Service Provider for enterprise SSO (Active Directory, Okta, Azure AD) |
| [Tenant](tenant.md) | Multi-tenancy with isolated databases and automatic resolution by domain or port |
| [UI Dashboard](ui-dashboard.md) | React admin interface for monitoring and managing a Fennec application |

---

*Update: `/doc update` — Regenerate this index: `/doc index`*
