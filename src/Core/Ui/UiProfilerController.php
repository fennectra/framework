<?php

namespace Fennec\Core\Ui;

use Fennec\Core\Profiler\Profiler;
use Fennec\Core\Response;

class UiProfilerController
{
    public function requests(): void
    {
        $profiler = Profiler::getInstance();

        if (!$profiler) {
            Response::json(['enabled' => false, 'requests' => []]);

            return;
        }

        $entries = $profiler->getAll();

        // Sort by most recent first
        usort($entries, fn ($a, $b) => ($b->startTime ?? 0) <=> ($a->startTime ?? 0));

        $requests = [];
        foreach ($entries as $entry) {
            $requests[] = [
                'id' => $entry->id,
                'method' => $entry->method,
                'uri' => $entry->uri,
                'route' => $entry->route,
                'statusCode' => $entry->statusCode,
                'durationMs' => $entry->durationMs,
                'memoryEnd' => $entry->memoryEnd,
                'peakMemory' => $entry->peakMemory,
                'queryCount' => count($entry->queries),
                'eventCount' => count($entry->events),
                'middlewareCount' => count($entry->middlewares),
                'n1Warnings' => $entry->n1Warnings,
                'startTime' => $entry->startTime,
            ];
        }

        Response::json([
            'enabled' => $profiler->isEnabled(),
            'requests' => $requests,
        ]);
    }

    public function show(string $id): void
    {
        $profiler = Profiler::getInstance();

        if (!$profiler) {
            Response::json(['error' => 'Profiler not available'], 503);

            return;
        }

        $entry = $profiler->getById($id);

        if (!$entry) {
            Response::json(['error' => 'Profile not found'], 404);

            return;
        }

        Response::json($entry->jsonSerialize());
    }

    public function clear(): void
    {
        Response::json(['success' => true, 'message' => 'Profiler data cleared (in-memory only, resets on next request cycle)']);
    }
}
