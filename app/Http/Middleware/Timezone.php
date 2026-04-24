<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class Timezone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $timezone = config('app.timezone');

        if ($request->session()->has('business_timezone')) {
            $timezone = $request->session()->get('business_timezone');
        } elseif ($request->session()->has('business')) {
            $business = $request->session()->get('business');
            $business_tz = is_object($business)
                ? ($business->time_zone ?? null)
                : ($business['time_zone'] ?? null);
            if (! empty($business_tz)) {
                $timezone = $business_tz;
            }
        } elseif (Auth::check() && optional(Auth::user()->business)->time_zone) {
            $timezone = Auth::user()->business->time_zone;
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        return $next($request);
    }
}
