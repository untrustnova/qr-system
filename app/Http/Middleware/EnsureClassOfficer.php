<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClassOfficer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->user_type !== 'student') {
            return new JsonResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (! optional($user->studentProfile)->is_class_officer) {
            return new JsonResponse(['message' => 'Forbidden for non class officer'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
