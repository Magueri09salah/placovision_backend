<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOdooApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = $request->header('X-PlacoVision-Api-Key');
        $expectedKey = config('services.odoo.webhook_api_key');

        if (empty($expectedKey)) {
            return response()->json([
                'message' => 'Webhook API key not configured on server.',
            ], 500);
        }

        if (empty($providedKey)) {
            return response()->json([
                'message' => 'Missing required header: X-PlacoVision-Api-Key',
            ], 401);
        }

        // hash_equals prevents timing attacks
        if (!hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'message' => 'Invalid API key.',
            ], 403);
        }

        return $next($request);
    }
}