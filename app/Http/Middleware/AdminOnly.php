<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return redirect('/login');
        }
        abort_unless($request->user()->role === 'admin', 403);

        return $next($request);
    }
}
