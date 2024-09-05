<?php

declare(strict_types=1);

namespace UseTheFork\Synapse\Utilities\Concerns;

use Illuminate\Support\Facades\Log;


trait HasLogging
{
    /**
     * Logs an event with optional context information.
     *
     * @param  string  $event  The event to be logged.
     * @param  array|null  $context  Optional context information.
     */
    protected function log(string $event, ?array $context = []): void
    {
        $class = get_class($this);
        Log::debug("{$event} in {$class}", $context);
    }
}
