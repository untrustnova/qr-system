<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminType
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (! $user || $user->user_type !== 'admin') {
            return new JsonResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $adminType = optional($user->adminProfile)->type;

        if (! in_array($adminType, $types, true)) {
            return new JsonResponse(['message' => 'Forbidden for admin type '.$adminType], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
