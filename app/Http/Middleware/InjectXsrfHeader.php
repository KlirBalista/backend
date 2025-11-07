<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectXsrfHeader
{
    /**
     * If the X-XSRF-TOKEN header is missing but the XSRF-TOKEN cookie exists,
     * copy the cookie value into the expected header so Laravel's CSRF
     * middleware can validate it in cross-site requests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->headers->has('X-XSRF-TOKEN')) {
            $cookie = $request->cookie('XSRF-TOKEN');
            if ($cookie) {
                $request->headers->set('X-XSRF-TOKEN', $cookie);
            }
        }

        return $next($request);
    }
}
