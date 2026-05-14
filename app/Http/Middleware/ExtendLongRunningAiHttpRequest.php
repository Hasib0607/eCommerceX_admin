<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Store AI preview / creation call the Python bot + OpenAI; default PHP max_execution_time (30s)
 * terminates Guzzle mid-read. Extend limits for these routes only.
 */
class ExtendLongRunningAiHttpRequest
{
    public function handle(Request $request, Closure $next)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        @ini_set('max_execution_time', '300');
        @ini_set('default_socket_timeout', '300');

        return $next($request);
    }
}
