<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account can be closed while its holder is working. Rather than leaving them staring at a page
 * that no longer means anything, the next click takes them out and tells them why.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isClosed()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'This account is closed. Ask the Chairperson to reopen it.');
        }

        return $next($request);
    }
}
