<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Event\SyncBroker;
use Fennec\Core\EventDispatcher;
use Fennec\Core\Response;

class UiEventsController
{
    public function listeners(): void
    {
        try {
            $dispatcher = EventDispatcher::getInstance();
            $broker = $dispatcher->broker();

            $result = [];

            // Only SyncBroker stores listeners in-memory
            if ($broker instanceof SyncBroker) {
                $reflection = new \ReflectionClass($broker);
                $listenersProp = $reflection->getProperty('listeners');
                $listenersProp->setAccessible(true);
                $listeners = $listenersProp->getValue($broker);

                foreach ($listeners as $event => $eventListeners) {
                    $items = [];
                    foreach ($eventListeners as $listener) {
                        $items[] = [
                            'type' => $this->describeCallable($listener['callback'] ?? $listener),
                            'priority' => $listener['priority'] ?? 0,
                        ];
                    }
                    $result[] = [
                        'event' => $event,
                        'listeners' => $items,
                        'count' => count($items),
                    ];
                }
            }

            usort($result, fn ($a, $b) => strcmp($a['event'], $b['event']));

            Response::json([
                'driver' => $dispatcher->driverName(),
                'events' => $result,
                'totalEvents' => count($result),
                'totalListeners' => array_sum(array_column($result, 'count')),
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 500);
        }
    }

    private function describeCallable(mixed $callable): string
    {
        if (is_array($callable)) {
            $class = is_object($callable[0]) ? get_class($callable[0]) : $callable[0];

            return $class . '::' . $callable[1];
        }

        if (is_string($callable)) {
            return $callable;
        }

        if ($callable instanceof \Closure) {
            $ref = new \ReflectionFunction($callable);

            return 'Closure@' . basename($ref->getFileName() ?: 'unknown') . ':' . $ref->getStartLine();
        }

        return 'unknown';
    }
}
