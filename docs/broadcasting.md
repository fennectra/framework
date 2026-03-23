# Broadcasting (SSE)

> Real-time event broadcasting via Server-Sent Events (SSE) and Redis Streams.

## Overview

The Broadcasting module allows pushing real-time events to browser clients via the **Server-Sent Events (SSE)** protocol. On the server side, events are published to a **Redis Stream** (`sse:broadcast`) by the `Broadcaster`, then consumed by the `SseController` which maintains a long-lived HTTP connection with each client.

The architecture relies on Redis Streams (not PUB/SUB) for three advantages:
- **Persistence**: messages are not lost if no listener is connected
- **Resumption**: the `Last-Event-ID` HTTP header allows resuming after a disconnection
- **Stability**: `XREAD BLOCK` does not break the connection on timeout (unlike SUBSCRIBE)

The stream is automatically trimmed to ~1000 maximum entries (approximate trim via `XTRIM ... MAXLEN ~ 1000`) after each `XADD` to prevent indefinite growth. The JSON payload stored in the stream contains the fields `channel`, `event`, `data` and `timestamp` (ISO 8601 format).

## Diagram

```mermaid
graph LR
    classDef chamois fill:#d4a574,color:#3b2314,stroke:#b8895a,stroke-width:2px
    classDef blanc fill:#fefefe,color:#3b2314,stroke:#d4a574,stroke-width:2px
    classDef beige fill:#f5e6d3,color:#3b2314,stroke:#d4a574,stroke-width:2px
    classDef success fill:#e8dcc8,color:#3b2314,stroke:#b8895a,stroke-width:2px
    classDef error fill:#c9a07a,color:#3b2314,stroke:#a07850,stroke-width:2px

    A["PHP Backend"] -->|broadcast()| B["Broadcaster"]
    B -->|XADD| C["Redis Stream"]
    C -->|XREAD BLOCK| D["SseController"]
    D -->|text/event-stream| E["Browser 1"]
    D -->|text/event-stream| F["Browser 2"]

    class A blanc
    class B chamois
    class C beige
    class D success
    class E,F error
```

## Public API

### Publishing events

```php
use Fennec\Core\Broadcasting\Broadcaster;
use Fennec\Core\Redis\RedisConnection;

$broadcaster = new Broadcaster(new RedisConnection());

// Broadcast an event on a channel
$broadcaster->broadcast('orders', 'order.created', [
    'id' => 42,
    'total' => 99.90,
    'customer' => 'Jean Dupont',
]);

// Broadcast on another channel
$broadcaster->broadcast('chat', 'message.new', [
    'user' => 'Alice',
    'text' => 'Hello!',
]);
```

### Client-side listening (JavaScript)

```javascript
// Listen to all channels
const sse = new EventSource('/sse/stream');

// Listen to a specific event
sse.addEventListener('order.created', (event) => {
    const data = JSON.parse(event.data);
    console.log('New order:', data);
});

// Listen to specific channels only
const sse2 = new EventSource('/sse/stream?channels=chat,orders');

// Heartbeat (SSE comment, handled automatically)
sse.onopen = () => console.log('Connected');
sse.onerror = () => console.log('Auto-reconnecting in 3s...');
```

### SSE Endpoint

The `SseController::stream()` is the HTTP entry point for SSE clients.

**Query params**:
- `?channels=chat,orders`: filter channels to listen to (all if omitted)

**Response headers**:
- `Content-Type: text/event-stream`
- `Cache-Control: no-cache`
- `Connection: keep-alive`
- `X-Accel-Buffering: no` (disables Nginx buffering)

**SSE event format**:

```
id: 1679000000000-0
event: order.created
data: {"channel":"orders","event":"order.created","data":{...},"timestamp":"2026-03-22T10:00:00+00:00"}
```

**Reconnection**:
- The client sends `Last-Event-ID` automatically after disconnection
- The server resumes from that ID in the Redis Stream
- `retry: 3000` tells the browser to reconnect after 3 seconds

**Heartbeat**:
- An SSE comment (`: heartbeat`) is sent every ~10 seconds (5 iterations x 2s BLOCK) to keep the connection alive

## Configuration

| Variable | Default | Description |
|---|---|---|
| `REDIS_HOST` | `127.0.0.1` | Redis host |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | `''` | Redis password |
| `REDIS_DB` | `0` | Redis database number |

### Internal Constants

| Constant | Value | Description |
|---|---|---|
| `STREAM_KEY` | `sse:broadcast` | Redis Stream key |
| `MAX_STREAM_LENGTH` | `1000` | Max stream size (auto trim via XTRIM) |
| `BLOCK_MS` | `2000` | XREAD BLOCK duration in milliseconds |
| `HEARTBEAT_EVERY` | `5` | Heartbeat every N empty iterations (~10s) |

## Integration with other modules

- **Redis**: uses `RedisConnection` for the `Broadcaster` and a direct `\Redis` connection for the `SseController` (dedicated long-lived connection)
- **Notifications**: notifications can trigger a `broadcast()` to notify connected clients in real time
- **Events**: an event listener can call `Broadcaster::broadcast()` to broadcast the event to SSE clients
- **Worker**: the `SseController` creates its own Redis connection with `close()` in a `finally` block for memory safety in worker mode

## Full Example

```php
// === Server side: publish an event after an action ===

use Fennec\Core\Broadcasting\Broadcaster;
use Fennec\Core\Redis\RedisConnection;

class OrderController
{
    public function create(): OrderResponse
    {
        // Create the order...
        $order = Order::create([...]);

        // Broadcast in real time
        $broadcaster = new Broadcaster(new RedisConnection());
        $broadcaster->broadcast('orders', 'order.created', [
            'id' => $order->id,
            'total' => $order->total,
            'status' => $order->status,
        ]);

        return new OrderResponse(status: 'ok', order: $order);
    }
}

// === Server side: SSE route ===
// In app/Routes/sse.php:
$router->get('/sse/stream', [SseController::class, 'stream']);

// === Client side: listen for orders ===
const sse = new EventSource('/sse/stream?channels=orders');

sse.addEventListener('order.created', (e) => {
    const order = JSON.parse(e.data);
    showNotification(`Order #${order.data.id} created!`);
});
```

## Module Files

| File | Role |
|---|---|
| `src/Core/Broadcasting/Broadcaster.php` | Event publishing via Redis Stream (XADD) |
| `src/Core/Broadcasting/SseController.php` | SSE controller with XREAD BLOCK and heartbeat |
