<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Log the attempt to access admin route
        Log::info('Admin route access attempt', [
            'user_id' => auth()->id(),
            'route' => $request->path()
        ]);

        // Check if user is logged in and is admin
        if (auth()->check() && auth()->user()->is_admin) {
            return $next($request);
        }

        // Log unauthorized attempt
        Log::warning('Unauthorized admin access attempt', [
            'user_id' => auth()->id(),
            'route' => $request->path()
        ]);

        // Return 403 response instead of redirecting
        if ($request->expectsJson()) {
            // For API requests
            return response()->json(['message' => 'Unauthorized access. Admin privileges required.'], 403);
        }

        // For web requests - you can either:
        // Option 1: Throw a 403 exception that will be rendered by Laravel
        abort(403, 'Unauthorized access. Admin privileges required.');
        
        // Option 2: Or redirect to a custom error page
        // return response()->view('errors.403', [], 403);
    }
} 