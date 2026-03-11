<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;

class VerifyCsrfUnlessMcp extends ValidateCsrfToken
{
    protected function tokensMatch($request): bool
    {
        if ($request->attributes->get('mcp_authenticated')) {
            return true;
        }

        return parent::tokensMatch($request);
    }
}
