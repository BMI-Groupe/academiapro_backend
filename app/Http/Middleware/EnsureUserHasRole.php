<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
	/**
	 * Handle an incoming request.
	 *
	 * Expected usage: ->middleware('role:administrateur,enseignant')
	 */
	public function handle(Request $request, Closure $next, string ...$roles): Response
	{
		$user = $request->user();

		if (!$user) {
			return response()->json([
				'success' => false,
				'data' => [],
				'message' => 'Non authentifié.'
			], 401);
		}

		if (empty($roles)) {
			return $next($request);
		}

		if (!in_array($user->role, $roles, true)) {
			// Log denied access for easier debugging
			try {
				Log::warning('Access denied by EnsureUserHasRole middleware', [
					'user_id' => $user->id ?? null,
					'user_role' => $user->role ?? null,
					'required_roles' => $roles,
					'path' => $request->path(),
				]);
			} catch (\Throwable $_) {
				// ignore logging failures
			}

			return response()->json([
				'success' => false,
				'data' => [],
				'message' => 'Vous n\'êtes pas autorisé à accéder à cette ressource.'
			], 403);
		}

		return $next($request);
	}
}


