<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  ...$guards
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                if ($request->expectsJson() || $request->is('react-admin-api/*')) {
                    return response()->json([
                        'message' => 'Already authenticated.',
                    ], 409);
                }

                $user = Auth::guard($guard)->user();

                if (!$user) {
                    return redirect(RouteServiceProvider::HOME);
                }

                if ($user->type === 'superadmin' || $user->type === 'superstaff') {
                    return redirect()->route('superadmin.index');
                }

                if ($user->type === 'admin' || $user->type === 'dropshipper') {
                    return redirect()->route('admin.index');
                }

                if ($user->type === 'staff') {
                    return redirect()->route('staff.dashboard');
                }

                if ($user->type === 'affiliate') {
                    return redirect()->route('affiliate.index');
                }

                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
