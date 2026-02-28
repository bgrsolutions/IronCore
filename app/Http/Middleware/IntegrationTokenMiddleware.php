<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiToken;
use Closure;
use Illuminate\Http\Request;

class IntegrationTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?: $request->header('X-Integration-Token');
        if (! $token) {
            abort(401, 'Missing integration token');
        }

        $hash = hash('sha256', $token);
        $record = IntegrationApiToken::query()->where('token_hash', $hash)->where('is_active', true)->first();
        if (! $record) {
            abort(401, 'Invalid integration token');
        }

        $record->update(['last_used_at' => now()]);
        app()->instance('integration.company_id', $record->company_id);

        return $next($request);
    }
}
