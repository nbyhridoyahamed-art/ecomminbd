<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every response from this app is JSON, a CSV/PDF download, or a redirect
 * — never HTML a browser should render as a page — so `default-src 'none'`
 * is safe globally, not just a same-origin default. HSTS is deliberately
 * not set here: it belongs at the reverse proxy/load balancer that
 * terminates TLS in production, and hardcoding it in the app would also
 * apply to plain-HTTP local dev, where it does nothing but risk a
 * browser-cached lockout once real HTTPS is involved.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'none'");

        return $response;
    }
}
