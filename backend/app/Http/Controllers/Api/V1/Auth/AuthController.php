<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly ImageStorage $images) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'phone' => $request->validated('phone'),
            'role' => UserRole::from($request->validated('role', UserRole::Customer->value)),
        ]);

        return response()->json([
            'message' => 'Welcome to Foogra. Your account is ready.',
            // The request is not authenticated as this user yet, so the
            // resource needs telling that the caller owns the account.
            'user' => (new UserResource($user))->withPrivateFields(),
            'token' => $user->createToken('foogra-spa')->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $this->assertNotRateLimited($request);

        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey($request));

            // Deliberately vague: never reveal whether the address is registered.
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        // Keep the session in play too, so cookie-based SPA auth also works.
        if ($request->hasSession()) {
            Auth::login($user, (bool) $request->boolean('remember'));
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Signed in successfully.',
            'user' => (new UserResource($user))->withPrivateFields(),
            'token' => $user->createToken(
                $request->validated('device_name') ?: 'foogra-spa'
            )->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke only the token that made this call, not every device.
        $request->user()?->currentAccessToken()?->delete();

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadCount(['restaurants', 'reviews', 'bookings']);

        return response()->json(['user' => new UserResource($user)]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('avatar');

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $this->images->replace(
                $request->file('avatar'),
                'avatars',
                $user->avatar_path
            );
        }

        $user->fill($data);

        // Changing the email invalidates the previous verification.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);

        // A password change should sign out every other device.
        $current = $request->user()->currentAccessToken();
        $user->tokens()->when($current, fn ($q) => $q->whereKeyNot($current->id))->delete();

        return response()->json(['message' => 'Password updated. Other devices have been signed out.']);
    }

    private function throttleKey(Request $request): string
    {
        return 'login:'.strtolower((string) $request->input('email')).'|'.$request->ip();
    }

    private function assertNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
        ]);
    }
}
