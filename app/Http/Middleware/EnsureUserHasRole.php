<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side role authorization gate, registered as the `role` middleware
 * alias. Usage: `middleware('role:customer')`, `middleware('role:lawyer,admin')`.
 *
 * This is the *only* thing that decides whether a request may proceed --
 * hidden buttons, JS, or localStorage state on the client have no bearing
 * on this check. A guest is redirected to login; an authenticated user
 * with the wrong role gets a 403, never a silent redirect that could hint
 * at what exists behind the wall.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        $allowed = collect($roles)
            ->map(fn (string $role) => strtolower(trim($role)))
            ->filter(fn (string $role) => UserRole::tryFrom($role) !== null);

        if ($allowed->isNotEmpty() && ! $allowed->contains($user->role->value)) {
            abort(403, 'You are not authorized to access this area of Legal Connect.');
        }

        return $next($request);
    }
}
