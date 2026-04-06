<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $admin = $request->user();
        
        if (!$admin || !$admin->isSuperAdmin()) {
            return response()->json([
                'message' => 'Accès réservé aux super administrateurs',
            ], 403);
        }
        
        return $next($request);
    }
}
