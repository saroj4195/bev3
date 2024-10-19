<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';
    protected $namespace = 'App\Http\Controllers';
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace) 
                ->group(base_path('routes/web.php'));

            // Route::middleware('web')
            //     ->prefix('paid_services')
            //     ->group(base_path('routes/web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/be-web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/bharatstay-web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/crs-web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/Extranetv4-web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/hotel-chain-web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/packagebooking-web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/rate-shopper-web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/be-v3.php'));

            // Route::middleware('api')
            // ->namespace($this->namespace) // <- This line must be inserted
            // ->group(base_path('routes/day-outing-package-web.php'));

            Route::middleware('api')
            ->namespace($this->namespace) // <- This line must be inserted
            ->group(base_path('routes/day-booking.php'));


            // Route::middleware('api')
            // ->namespace($this->namespace) // <- This line must be inserted
            // ->group(base_path('routes/agent-dev-web.php'));

        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
