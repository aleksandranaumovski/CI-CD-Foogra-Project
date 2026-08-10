<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Dish;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\DishPolicy;
use App\Policies\MenuSectionPolicy;
use App\Policies\RestaurantPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerRateLimiters();

        /*
         * Reset links must land on the React app, not on an API route. The SPA
         * reads both values off the query string and POSTs them back to
         * /api/v1/auth/reset-password.
         */
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return rtrim((string) config('app.frontend_url'), '/')
                ."/reset-password?token={$token}&email={$email}";
        });

        /*
         * Fail loudly in development when a mass-assignment silently drops a
         * field, instead of shipping quietly-ignored input.
         *
         * Lazy-load prevention is deliberately NOT enabled: policies and model
         * observers resolve relations on demand by design (a policy is handed a
         * bare model and must reach for ->restaurant to check ownership), so it
         * would fire on correct code rather than on real N+1s.
         */
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());
    }

    private function registerRateLimiters(): void
    {
        /*
         * Credential endpoints are the ones worth guarding hard — the login
         * controller additionally throttles per email+IP, so a shared office
         * IP cannot lock out one person's account by attacking another's.
         */
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        // Browsing is cheap and cacheable; be generous, and count per user when known.
        RateLimiter::for('public', fn (Request $request) => [
            Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => [
            Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()),
        ]);
    }

    private function registerPolicies(): void
    {
        Gate::policy(Restaurant::class, RestaurantPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(MenuSection::class, MenuSectionPolicy::class);
        Gate::policy(Dish::class, DishPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
