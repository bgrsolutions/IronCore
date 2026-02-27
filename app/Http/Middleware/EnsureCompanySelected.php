<?php

namespace App\Http\Middleware;

use App\Support\Company\CompanyContext;
use Closure;
use Illuminate\Http\Request;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs('filament.admin.pages.company-context')) {
            return $next($request);
        }

        if (CompanyContext::get() === null) {
            return redirect()->route('filament.admin.pages.company-context');
        }

        return $next($request);
    }
}
