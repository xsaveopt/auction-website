<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;

class VerifyCsrfUnlessApiKey extends ValidateCsrfToken
{
    protected function tokensMatch($request): bool
    {
        if ($request->attributes->get('api_key_authenticated')) {
            return true;
        }

        return parent::tokensMatch($request);
    }
}
