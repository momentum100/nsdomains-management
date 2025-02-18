<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        return redirect('/')->with('error', 'Unauthorized access');
    }
} 