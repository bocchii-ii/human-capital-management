<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('X-Tenant') ?? $request->query('tenant');

        if ($slug) {
            $tenant = Tenant::where('slug', $slug)->where('is_active', true)->first();

            if (! $tenant) {
                return response()->json(['message' => 'Tenant not found.'], 404);
            }

            app()->instance('tenant', $tenant);
        }

        return $next($request);
    }
}
