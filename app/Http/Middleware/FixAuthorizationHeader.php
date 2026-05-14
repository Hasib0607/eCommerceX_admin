<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FixAuthorizationHeader
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->headers->has('Authorization')) {
            $header = $request->header('X-Authorization')
                ?: $request->server('HTTP_AUTHORIZATION')
                ?: $request->server('REDIRECT_HTTP_AUTHORIZATION');

            if (!$header && function_exists('getallheaders')) {
                $headers = getallheaders();
                $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            }

            if (!$header && function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            }

            if ($header) {
                $request->headers->set('Authorization', $header);
            }
        }

        return $next($request);
    }
}
