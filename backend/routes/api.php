<?php

use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\DishController;
use App\Http\Controllers\Api\V1\Admin\MenuSectionController;
use App\Http\Controllers\Api\V1\Admin\RestaurantController as AdminRestaurantController;
use App\Http\Controllers\Api\V1\Admin\ReviewModerationController;
use App\Http\Controllers\Api\V1\Admin\StatsController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\Public\CategoryController;
use App\Http\Controllers\Api\V1\Public\RestaurantController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Foogra API — v1
|--------------------------------------------------------------------------
|
| Three tiers:
|   • public  — anything the site renders without a login
|   • auth    — a signed-in diner's own reviews, bookings and wishlist
|   • admin   — the dashboard, split by role between admins and owners
|
*/

Route::prefix('v1')->group(function (): void {

    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]))->name('health');

    /*
    |----------------------------------------------------------------------
    | Authentication
    |----------------------------------------------------------------------
    */
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('/register', [AuthController::class, 'register'])->name('register');
            Route::post('/login', [AuthController::class, 'login'])->name('login');
            Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
            Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::match(['put', 'patch'], '/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
            Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Public catalogue
    |----------------------------------------------------------------------
    | Reachable without a token. When a token *is* present these endpoints
    | also return `is_wishlisted` and the viewer's own review votes.
    */
    Route::middleware('throttle:public')->group(function (): void {
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

        // Static segments must be declared before the {restaurant} wildcard.
        Route::get('/restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
        Route::get('/restaurants/featured', [RestaurantController::class, 'featured'])->name('restaurants.featured');
        Route::get('/restaurants/deals', [RestaurantController::class, 'deals'])->name('restaurants.deals');
        Route::get('/restaurants/suggestions', [RestaurantController::class, 'suggestions'])->name('restaurants.suggestions');
        Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])->name('restaurants.show');
        Route::get('/restaurants/{restaurant}/menu', [RestaurantController::class, 'menu'])->name('restaurants.menu');
        Route::get('/restaurants/{restaurant}/availability', [RestaurantController::class, 'availability'])->name('restaurants.availability');
        Route::get('/restaurants/{restaurant}/reviews', [ReviewController::class, 'index'])->name('restaurants.reviews');

        // Guests may book a table, and look one up with reference + email.
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    });

    /*
    |----------------------------------------------------------------------
    | Signed-in diner
    |----------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function (): void {

        // Reviews
        Route::post('/restaurants/{restaurant}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        Route::get('/reviews/mine', [ReviewController::class, 'mine'])->name('reviews.mine');
        Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');
        Route::match(['put', 'patch'], '/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::post('/reviews/{review}/vote', [ReviewController::class, 'vote'])->name('reviews.vote');
        Route::delete('/reviews/{review}/vote', [ReviewController::class, 'removeVote'])->name('reviews.vote.destroy');

        // The owner's public answer to a review.
        Route::post('/reviews/{review}/reply', [ReviewController::class, 'reply'])->name('reviews.reply');
        Route::delete('/reviews/{review}/reply', [ReviewController::class, 'destroyReply'])->name('reviews.reply.destroy');

        // Bookings
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::match(['put', 'patch'], '/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

        // Wishlist
        Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
        Route::post('/wishlist/{restaurant}/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
        Route::delete('/wishlist/{restaurant}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
    });

    /*
     * Declared last so `/bookings/mine`-style literals above win over the
     * {booking} wildcard. Optional auth: a guest passes ?email=… instead.
     */
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->middleware('throttle:public')
        ->name('bookings.show');

    /*
    |----------------------------------------------------------------------
    | Dashboard — admins and restaurant owners
    |----------------------------------------------------------------------
    | The role middleware is the coarse gate; per-record ownership is
    | enforced by the policies inside each controller.
    */
    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['auth:sanctum', 'role:admin,owner'])
        ->group(function (): void {

            Route::get('/stats', StatsController::class)->name('stats');

            // Restaurants
            Route::get('/restaurants', [AdminRestaurantController::class, 'index'])->name('restaurants.index');
            Route::post('/restaurants', [AdminRestaurantController::class, 'store'])->name('restaurants.store');
            Route::post('/restaurants/{id}/restore', [AdminRestaurantController::class, 'restore'])->name('restaurants.restore');
            Route::delete('/restaurants/{id}/force', [AdminRestaurantController::class, 'forceDestroy'])->name('restaurants.force-destroy');
            Route::get('/restaurants/{restaurant}', [AdminRestaurantController::class, 'show'])->name('restaurants.show');
            Route::match(['put', 'patch'], '/restaurants/{restaurant}', [AdminRestaurantController::class, 'update'])->name('restaurants.update');
            Route::delete('/restaurants/{restaurant}', [AdminRestaurantController::class, 'destroy'])->name('restaurants.destroy');

            // Gallery + timetable
            Route::post('/restaurants/{restaurant}/images', [AdminRestaurantController::class, 'storeImages'])->name('restaurants.images.store');
            Route::delete('/restaurant-images/{image}', [AdminRestaurantController::class, 'destroyImage'])->name('restaurants.images.destroy');
            Route::put('/restaurants/{restaurant}/opening-hours', [AdminRestaurantController::class, 'syncOpeningHours'])->name('restaurants.hours.sync');

            // Menu
            Route::put('/menu-sections/reorder', [MenuSectionController::class, 'reorder'])->name('menu-sections.reorder');
            Route::apiResource('menu-sections', MenuSectionController::class);
            Route::patch('/dishes/{dish}/availability', [DishController::class, 'toggleAvailability'])->name('dishes.availability');
            Route::apiResource('dishes', DishController::class);

            // Review moderation
            Route::get('/reviews', [ReviewModerationController::class, 'index'])->name('reviews.index');
            Route::patch('/reviews/{review}/status', [ReviewModerationController::class, 'updateStatus'])->name('reviews.status');
            Route::delete('/reviews/{review}', [ReviewModerationController::class, 'destroy'])->name('reviews.destroy');

            // Reservation book
            Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
            Route::get('/bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
            Route::match(['put', 'patch'], '/bookings/{booking}', [AdminBookingController::class, 'update'])->name('bookings.update');
            Route::post('/bookings/{booking}/transition', [AdminBookingController::class, 'transition'])->name('bookings.transition');
            Route::delete('/bookings/{booking}', [AdminBookingController::class, 'destroy'])->name('bookings.destroy');

            // Categories — readable by owners, writable by admins (CategoryPolicy).
            Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
            Route::post('/categories/{id}/restore', [AdminCategoryController::class, 'restore'])->name('categories.restore');
            Route::get('/categories/{category}', [AdminCategoryController::class, 'show'])->name('categories.show');
            Route::match(['put', 'patch'], '/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

            // Users — admin only (UserPolicy).
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::post('/users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
});
