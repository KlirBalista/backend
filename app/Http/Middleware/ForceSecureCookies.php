<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceSecureCookies
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Force all cookies to have SameSite=None, Secure, and Partitioned for cross-site support
        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->setCookie(
                new \Symfony\Component\HttpFoundation\Cookie(
                    $cookie->getName(),
                    $cookie->getValue(),
                    $cookie->getExpiresTime(),
                    $cookie->getPath(),
                    $cookie->getDomain(),
                    true, // secure - always true for production
                    $cookie->isHttpOnly(),
                    false, // raw
                    'none', // sameSite - force none for cross-site
                    true // partitioned - enable CHIPS
                )
            );
        }

        return $response;
    }
}
